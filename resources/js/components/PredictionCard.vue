<template>
  <div class="prediction-card" :class="{ 'answered': hasAnswer, 'resolved': question.is_resolved }">
    <!-- Category Badge -->
    <div class="category-badge" :style="{ backgroundColor: question.category?.color || '#6b7280' }">
      <span class="category-icon">{{ question.category?.icon || '❓' }}</span>
      <span class="category-name">{{ question.category?.name || 'General' }}</span>
    </div>

    <!-- Question Content -->
    <div class="question-content">
      <h3 class="question-title">{{ question.title }}</h3>
      <div class="question-meta">
        <span class="resolution-time">
          <span class="meta-icon">⏰</span>
          Resolves {{ formatResolutionTime }}
        </span>
        <span v-if="question.is_resolved" class="resolved-indicator">
          <span class="meta-icon">✅</span>
          Resolved
        </span>
      </div>
    </div>

    <!-- Options -->
    <div class="prediction-options">
      <button
        v-for="option in options"
        :key="option.value"
        @click="selectOption(option.value)"
        :disabled="question.is_resolved || isSubmitting"
        class="option-button"
        :class="{
          'selected': selectedChoice === option.value,
          'correct': question.is_resolved && question.correct_answer === option.value,
          'incorrect': question.is_resolved && userPrediction?.choice === option.value && question.correct_answer !== option.value,
          'unselected': question.is_resolved && userPrediction?.choice !== option.value
        }"
      >
        <div class="option-content">
          <span class="option-text">{{ option.text }}</span>
          <div class="option-stats" v-if="showStats">
            <span class="option-percentage">{{ option.percentage }}%</span>
            <div class="option-bar">
              <div 
                class="option-bar-fill" 
                :style="{ width: `${option.percentage}%` }"
              ></div>
            </div>
          </div>
        </div>
        
        <!-- Result indicator for resolved questions -->
        <div v-if="question.is_resolved" class="result-indicator">
          <span v-if="question.correct_answer === option.value" class="result-icon correct">✓</span>
          <span v-else-if="userPrediction?.choice === option.value" class="result-icon incorrect">✗</span>
        </div>
      </button>
    </div>

    <!-- Betting Section -->
    <div v-if="!question.is_resolved && selectedChoice" class="betting-section">
      <div class="betting-header">
        <label class="betting-label">Bet Amount</label>
        <div class="coin-balance">
          <span class="balance-icon">🪙</span>
          <span class="balance-amount">{{ userCoins }} coins</span>
        </div>
      </div>
      
      <BettingSlider
        v-model="betAmount"
        :min="minBet"
        :max="maxBet"
        :userCoins="userCoins"
        @update:modelValue="updateBetAmount"
      />
      
      <div class="betting-info">
        <div class="potential-winnings">
          <span class="info-label">Potential Winnings:</span>
          <span class="info-value">{{ potentialWinnings }} coins</span>
        </div>
        <div class="multiplier">
          <span class="info-label">Multiplier:</span>
          <span class="info-value">{{ multiplier }}x</span>
        </div>
      </div>
    </div>

    <!-- User's Prediction (if answered) -->
    <div v-if="userPrediction" class="user-prediction">
      <div class="prediction-header">
        <span class="prediction-icon">🎯</span>
        <span class="prediction-text">Your Prediction</span>
      </div>
      <div class="prediction-details">
        <div class="prediction-choice">
          <span class="choice-label">Choice:</span>
          <span class="choice-value">{{ getUserChoiceText(userPrediction.choice) }}</span>
        </div>
        <div class="prediction-bet">
          <span class="bet-label">Bet:</span>
          <span class="bet-value">{{ userPrediction.bet_amount }} coins</span>
        </div>
        <div v-if="question.is_resolved" class="prediction-result">
          <span class="result-label">Result:</span>
          <span class="result-value" :class="{ 'win': userPrediction.is_correct, 'loss': !userPrediction.is_correct }">
            {{ userPrediction.is_correct ? '+' : '' }}{{ userPrediction.actual_winnings || 0 }} coins
          </span>
        </div>
      </div>
    </div>

    <!-- Action Button -->
    <div v-if="!userPrediction && !question.is_resolved" class="card-actions">
      <button
        @click="submitPrediction"
        :disabled="!canSubmit"
        class="submit-button"
        :class="{ 'ready': canSubmit }"
      >
        <span class="button-icon">🎯</span>
        <span class="button-text">
          {{ isSubmitting ? 'Submitting...' : 'Submit Prediction' }}
        </span>
      </button>
    </div>

    <!-- Resolution Banner -->
    <div v-if="question.is_resolved && userPrediction" class="resolution-banner" :class="{ 'win': userPrediction.is_correct, 'loss': !userPrediction.is_correct }">
      <div class="banner-content">
        <span class="banner-icon">{{ userPrediction.is_correct ? '🎉' : '😢' }}</span>
        <span class="banner-text">
          {{ userPrediction.is_correct ? 'Correct Prediction!' : 'Better luck next time!' }}
        </span>
        <span class="banner-amount">
          {{ userPrediction.is_correct ? '+' : '' }}{{ userPrediction.actual_winnings || 0 }} coins
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import BettingSlider from './BettingSlider.vue';

// Props
interface Props {
  question: {
    id: number;
    title: string;
    option_a: string;
    option_b: string;
    resolution_time: string;
    is_resolved: boolean;
    correct_answer?: string;
    category?: {
      id: number;
      name: string;
      icon: string;
      color: string;
    };
  };
  userPrediction?: {
    id: number;
    choice: string;
    bet_amount: number;
    potential_winnings: number;
    actual_winnings?: number;
    is_correct?: boolean;
  };
  userCoins: number;
  showStats?: boolean;
  minBet?: number;
  maxBet?: number;
}

const props = withDefaults(defineProps<Props>(), {
  showStats: false,
  minBet: 10,
  maxBet: 500
});

// Emits
const emit = defineEmits<{
  predictionMade: [data: { questionId: number; choice: string; betAmount: number }];
}>();

// Reactive state
const selectedChoice = ref<string>('');
const betAmount = ref<number>(props.minBet);
const isSubmitting = ref<boolean>(false);

// Computed properties
const hasAnswer = computed(() => !!props.userPrediction);

const options = computed(() => [
  {
    value: 'A',
    text: props.question.option_a,
    percentage: 45 // Mock percentage, would come from backend
  },
  {
    value: 'B',
    text: props.question.option_b,
    percentage: 55 // Mock percentage, would come from backend
  }
]);

const formatResolutionTime = computed(() => {
  const date = new Date(props.question.resolution_time);
  const now = new Date();
  const tomorrow = new Date(now);
  tomorrow.setDate(tomorrow.getDate() + 1);
  
  if (date.toDateString() === now.toDateString()) {
    return `today at ${date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
  } else if (date.toDateString() === tomorrow.toDateString()) {
    return `tomorrow at ${date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
  } else {
    return date.toLocaleDateString('en-US', { 
      month: 'short', 
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit'
    });
  }
});

const multiplier = computed(() => {
  // Simple multiplier calculation based on perceived odds
  // In real app, this would come from backend based on betting pool
  const baseMultiplier = selectedChoice.value === 'A' ? 2.2 : 1.8;
  return Number(baseMultiplier.toFixed(1));
});

const potentialWinnings = computed(() => {
  return Math.round(betAmount.value * multiplier.value);
});

const canSubmit = computed(() => {
  return selectedChoice.value && 
         betAmount.value >= props.minBet && 
         betAmount.value <= Math.min(props.maxBet, props.userCoins) && 
         !isSubmitting.value;
});

// Methods
const selectOption = (choice: string) => {
  if (props.question.is_resolved) return;
  selectedChoice.value = choice;
};

const updateBetAmount = (amount: number) => {
  betAmount.value = amount;
};

const getUserChoiceText = (choice: string) => {
  return choice === 'A' ? props.question.option_a : props.question.option_b;
};

const submitPrediction = async () => {
  if (!canSubmit.value) return;
  
  isSubmitting.value = true;
  
  try {
    await router.post('/predictions', {
      question_id: props.question.id,
      choice: selectedChoice.value,
      bet_amount: betAmount.value
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        emit('predictionMade', {
          questionId: props.question.id,
          choice: selectedChoice.value,
          betAmount: betAmount.value
        });
        
        // Reset form
        selectedChoice.value = '';
        betAmount.value = props.minBet;
      },
      onError: (errors) => {
        console.error('Prediction submission failed:', errors);
      }
    });
  } catch (error) {
    console.error('Error submitting prediction:', error);
  } finally {
    isSubmitting.value = false;
  }
};

// Watch for bet amount changes to ensure it stays within bounds
watch([() => props.userCoins, () => props.maxBet], () => {
  const maxAllowed = Math.min(props.maxBet, props.userCoins);
  if (betAmount.value > maxAllowed) {
    betAmount.value = Math.max(props.minBet, maxAllowed);
  }
});
</script>

<style scoped>
.prediction-card {
  background: var(--tg-color-bg-secondary, white);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.prediction-card.answered {
  border-left: 4px solid var(--tg-color-button, #2563eb);
}

.prediction-card.resolved {
  opacity: 0.9;
}

/* Category Badge */
.category-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 2rem;
  color: white;
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

.category-icon {
  font-size: 1rem;
}

.category-name {
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

/* Question Content */
.question-content {
  margin-bottom: 1.5rem;
}

.question-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 0.75rem;
  line-height: 1.4;
}

.question-meta {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.resolution-time,
.resolved-indicator {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.875rem;
  color: var(--tg-color-hint, #6b7280);
}

.resolved-indicator {
  color: #10b981;
  font-weight: 500;
}

.meta-icon {
  font-size: 1rem;
}

/* Prediction Options */
.prediction-options {
  display: grid;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.option-button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border: 2px solid var(--tg-color-bg, #f1f5f9);
  border-radius: 0.75rem;
  background: var(--tg-color-bg, #f8fafc);
  color: var(--tg-color-text, #1f2937);
  font-size: 1rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.option-button:hover:not(:disabled) {
  border-color: var(--tg-color-button, #2563eb);
  background: var(--tg-color-bg-secondary, white);
}

.option-button.selected {
  border-color: var(--tg-color-button, #2563eb);
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
}

.option-button.correct {
  border-color: #10b981;
  background: #d1fae5;
  color: #065f46;
}

.option-button.incorrect {
  border-color: #ef4444;
  background: #fee2e2;
  color: #991b1b;
}

.option-button.unselected {
  opacity: 0.6;
}

.option-button:disabled {
  cursor: not-allowed;
}

.option-content {
  flex: 1;
  text-align: left;
}

.option-text {
  display: block;
  margin-bottom: 0.5rem;
}

.option-stats {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.option-percentage {
  font-size: 0.875rem;
  font-weight: 600;
  min-width: 3rem;
}

.option-bar {
  flex: 1;
  height: 0.25rem;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 0.125rem;
  overflow: hidden;
}

.option-bar-fill {
  height: 100%;
  background: currentColor;
  transition: width 0.3s ease;
}

.result-indicator {
  flex-shrink: 0;
  margin-left: 1rem;
}

.result-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  font-weight: bold;
  font-size: 0.875rem;
}

.result-icon.correct {
  background: #10b981;
  color: white;
}

.result-icon.incorrect {
  background: #ef4444;
  color: white;
}

/* Betting Section */
.betting-section {
  background: var(--tg-color-bg, #f8fafc);
  border-radius: 0.75rem;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.betting-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.betting-label {
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
}

.coin-balance {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.875rem;
  color: var(--tg-color-hint, #6b7280);
}

.balance-icon {
  font-size: 1rem;
}

.betting-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1rem;
}

.potential-winnings,
.multiplier {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--tg-color-bg-secondary, white);
  border-radius: 0.5rem;
}

.info-label {
  font-size: 0.875rem;
  color: var(--tg-color-hint, #6b7280);
}

.info-value {
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
}

/* User Prediction */
.user-prediction {
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  border: 1px solid #0ea5e9;
  border-radius: 0.75rem;
  padding: 1.25rem;
  margin-bottom: 1rem;
}

.prediction-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  font-weight: 600;
  color: #0369a1;
}

.prediction-icon {
  font-size: 1.125rem;
}

.prediction-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.prediction-choice,
.prediction-bet,
.prediction-result {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.choice-label,
.bet-label,
.result-label {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
}

.choice-value,
.bet-value {
  font-weight: 500;
  color: var(--tg-color-text, #1f2937);
}

.result-value {
  font-weight: 600;
}

.result-value.win {
  color: #10b981;
}

.result-value.loss {
  color: #ef4444;
}

/* Action Button */
.card-actions {
  margin-top: 1rem;
}

.submit-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 1rem;
  border: none;
  border-radius: 0.75rem;
  background: var(--tg-color-bg, #f1f5f9);
  color: var(--tg-color-hint, #6b7280);
  font-weight: 600;
  cursor: not-allowed;
  transition: all 0.2s ease;
}

.submit-button.ready {
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
  cursor: pointer;
}

.submit-button.ready:hover {
  background: #1d4ed8;
}

.button-icon {
  font-size: 1.125rem;
}

/* Resolution Banner */
.resolution-banner {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  padding: 0.75rem 1.5rem;
  text-align: center;
  font-weight: 600;
  color: white;
}

.resolution-banner.win {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.resolution-banner.loss {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.banner-content {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
}

.banner-icon {
  font-size: 1.25rem;
}

.banner-text {
  font-size: 0.875rem;
}

.banner-amount {
  font-weight: bold;
}

@media (max-width: 640px) {
  .prediction-card {
    padding: 1rem;
  }
  
  .question-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .option-button {
    padding: 0.875rem;
  }
  
  .betting-info {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
  
  .prediction-details {
    gap: 0.75rem;
  }
  
  .banner-content {
    gap: 0.5rem;
  }
  
  .banner-text {
    font-size: 0.8125rem;
  }
}
</style>