<template>
  <div class="questions-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-info">
          <h1 class="page-title">🎯 Daily Questions</h1>
          <p class="page-subtitle">Make your predictions and win coins!</p>
        </div>
        <div class="user-coins">
          <span class="coin-icon">🪙</span>
          <span class="coins-amount">{{ formatNumber(user.daily_coins) }}</span>
        </div>
      </div>
      
      <!-- Progress Bar -->
      <div class="progress-section">
        <div class="progress-header">
          <span class="progress-text">Progress: {{ answeredCount }} / {{ (questions && Array.isArray(questions)) ? questions.length : 0 }}</span>
          <span class="time-remaining">⏰ {{ timeUntilReset || 'Loading...' }}</span>
        </div>
        <div class="progress-bar">
          <div 
            class="progress-fill" 
            :style="{ width: progressPercentage + '%' }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Streak Display -->
    <StreakDisplay 
      :current-streak="user?.current_streak || 0"
      :streak-multiplier="user?.streak_multiplier || 1"
      :best-streak="user?.best_streak || 0"
      class="streak-display"
    />

    <!-- Questions Grid -->
    <div class="questions-container">
      <div class="questions-grid" v-if="questions && Array.isArray(questions) && questions.length > 0">
        <PredictionCard
          v-for="(question, index) in questions"
          :key="question?.id || `question-${index}-${question?.title || 'unknown'}`"
          :question="question"
          :user-prediction="question?.user_prediction"
          :user-coins="user?.daily_coins || 0"
          :streak-multiplier="user?.streak_multiplier || 1"
          @prediction-made="handlePredictionMade"
          class="question-card"
          v-if="question"
        />
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state">
        <div class="empty-icon">📅</div>
        <h2 class="empty-title">No Questions Available</h2>
        <p class="empty-description">
          New daily questions will be available soon. Check back later!
        </p>
        <Link 
          :href="dashboard.url()" 
          class="btn btn-primary"
        >
          🏠 Back to Dashboard
        </Link>
      </div>

      <!-- Completed State -->
      <div v-if="allQuestionsAnswered && questions && questions.length > 0" class="completed-overlay">
        <div class="completed-content">
          <div class="completed-icon">🎉</div>
          <h2 class="completed-title">All Questions Completed!</h2>
          <p class="completed-description">
            Great job! You've answered all {{ (questions && Array.isArray(questions)) ? questions.length : 0 }} questions today.
            <br>Come back tomorrow for new challenges!
          </p>
          
          <!-- Summary Stats -->
          <div class="completion-stats">
            <div class="stat-item">
              <div class="stat-value">{{ correctPredictions }}</div>
              <div class="stat-label">Correct</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ totalWinnings }}</div>
              <div class="stat-label">Coins Won</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ user?.current_streak || 0 }}</div>
              <div class="stat-label">Current Streak</div>
            </div>
          </div>

          <div class="completion-actions">
            <Link 
              :href="leaderboard.index.url()" 
              class="btn btn-secondary"
            >
              🏆 View Leaderboard
            </Link>
            <Link 
              :href="dashboard.url()" 
              class="btn btn-primary"
            >
              🏠 Dashboard
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Tips -->
    <div class="tips-section" v-if="!allQuestionsAnswered">
      <h3 class="tips-title">💡 Quick Tips</h3>
      <div class="tips-grid">
        <div class="tip-item">
          <div class="tip-icon">🔥</div>
          <div class="tip-content">
            <div class="tip-title">Build Streaks</div>
            <div class="tip-description">Correct predictions in a row increase your multiplier!</div>
          </div>
        </div>
        <div class="tip-item">
          <div class="tip-icon">💰</div>
          <div class="tip-content">
            <div class="tip-title">Smart Betting</div>
            <div class="tip-description">Bet more when you're confident, less when uncertain.</div>
          </div>
        </div>
        <div class="tip-item">
          <div class="tip-icon">⏰</div>
          <div class="tip-content">
            <div class="tip-title">Daily Reset</div>
            <div class="tip-description">New questions and fresh coins every day!</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PredictionCard from '@/components/PredictionCard.vue';
import StreakDisplay from '@/components/StreakDisplay.vue';
import { initializeTelegramMock } from '@/lib/telegram-mock';
// Import Wayfinder routes
import { dashboard } from '@/routes';
import leaderboard from '@/routes/leaderboard';

// Props
interface User {
  id: number;
  name: string;
  avatar?: string;
  daily_coins: number;
  current_streak: number;
  streak_multiplier: number;
  best_streak: number;
}

interface Question {
  id: number;
  title: string;
  description?: string;
  option_a: string;
  option_b: string;
  category: {
    id: number;
    name: string;
    color: string;
    icon: string;
  };
  resolution_time: string;
  is_resolved: boolean;
  correct_answer?: string;
  user_prediction?: {
    id: number;
    choice: 'A' | 'B';
    bet_amount: number;
    potential_winnings: number;
    actual_winnings?: number;
    is_correct?: boolean;
    created_at?: string;
  };
}

const props = defineProps<{
  user: User;
  questions?: Question[];
  timeUntilReset: string;
}>();

// Reactive data
const telegram = ref<any>(null);

// Computed properties
const answeredCount = computed(() => {
  if (!props.questions || !Array.isArray(props.questions)) return 0;
  return props.questions.filter(q => q && q.user_prediction).length;
});

const progressPercentage = computed(() => {
  if (!props.questions || !Array.isArray(props.questions) || props.questions.length === 0) return 0;
  return (answeredCount.value / props.questions.length) * 100;
});

const allQuestionsAnswered = computed(() => {
  return props.questions && Array.isArray(props.questions) && props.questions.length > 0 && answeredCount.value === props.questions.length;
});

const correctPredictions = computed(() => {
  if (!props.questions || !Array.isArray(props.questions)) return 0;
  return props.questions.filter(q => 
    q && q.user_prediction && 
    // We don't know if they're correct yet, so just count answered ones
    q.user_prediction
  ).length;
});

const totalWinnings = computed(() => {
  if (!props.questions || !Array.isArray(props.questions)) return 0;
  return props.questions
    .filter(q => q && q.user_prediction)
    .reduce((total, q) => total + (q.user_prediction?.potential_winnings || 0), 0);
});

// Methods
const formatNumber = (num: number): string => {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M';
  } else if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K';
  }
  return num.toString();
};

const handlePredictionMade = (data: any) => {
  // Provide haptic feedback
  if (telegram.value?.HapticFeedback) {
    telegram.value.HapticFeedback.notificationOccurred('success');
  }
  
  // Show completion overlay if all questions are now answered
  if (props.questions && Array.isArray(props.questions) && props.questions.length > 0) {
    const newAnsweredCount = answeredCount.value + 1;
    if (newAnsweredCount === props.questions.length) {
      // Trigger confetti or celebration animation
      if (telegram.value?.HapticFeedback) {
        telegram.value.HapticFeedback.notificationOccurred('success');
      }
    }
  }
  
  // Refresh the page to show updated data
  router.reload({ only: ['questions', 'user'] });
};

// Lifecycle
onMounted(() => {
  // Initialize Telegram WebApp
  telegram.value = initializeTelegramMock();
  
  if (telegram.value) {
    telegram.value.ready();
    telegram.value.expand();
    
    // Set main button if not all questions answered
    if (!allQuestionsAnswered.value && props.questions && Array.isArray(props.questions) && props.questions.length > 0) {
      telegram.value.MainButton.setText('View Results');
      telegram.value.MainButton.onClick(() => {
        router.visit(dashboard.url());
      });
      
      // Show main button only if at least one question is answered
      if (answeredCount.value > 0) {
        telegram.value.MainButton.show();
      }
    }
    
    // Set theme
    document.documentElement.style.setProperty('--tg-theme-bg-color', telegram.value.themeParams.bg_color || '#ffffff');
    document.documentElement.style.setProperty('--tg-theme-text-color', telegram.value.themeParams.text_color || '#000000');
    document.documentElement.style.setProperty('--tg-theme-button-color', telegram.value.themeParams.button_color || '#2481cc');
  }
});
</script>

<style scoped>
.questions-page {
  min-height: 100vh;
  background: linear-gradient(to bottom, var(--tg-theme-bg-color, #f8fafc), #ffffff);
  padding: 1rem;
  padding-bottom: 2rem;
}

/* Header */
.page-header {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.header-info {
  flex: 1;
}

.page-title {
  font-size: 1.75rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 0.5rem 0;
}

.page-subtitle {
  color: #6b7280;
  margin: 0;
  font-size: 1rem;
}

.user-coins {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #fef3c7;
  color: #d97706;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  font-weight: 600;
  border: 1px solid #fcd34d;
}

.coin-icon {
  font-size: 1.25rem;
}

.coins-amount {
  font-size: 1.25rem;
}

/* Progress Section */
.progress-section {
  margin-top: 1.5rem;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.progress-bar {
  width: 100%;
  height: 0.5rem;
  background: #e5e7eb;
  border-radius: 0.25rem;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--tg-theme-button-color, #2481cc), #10b981);
  border-radius: 0.25rem;
  transition: width 0.5s ease;
}

/* Streak Display */
.streak-display {
  margin-bottom: 1.5rem;
}

/* Questions Container */
.questions-container {
  position: relative;
  margin-bottom: 2rem;
}

.questions-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: white;
  border-radius: 1rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.empty-icon {
  font-size: 5rem;
  margin-bottom: 1.5rem;
  opacity: 0.5;
}

.empty-title {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 1rem 0;
}

.empty-description {
  color: #6b7280;
  margin: 0 0 2rem 0;
  line-height: 1.6;
}

/* Completed Overlay */
.completed-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.completed-content {
  background: white;
  border-radius: 1.5rem;
  padding: 2rem;
  text-align: center;
  max-width: 400px;
  width: 100%;
  max-height: 80vh;
  overflow-y: auto;
}

.completed-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.completed-title {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 1rem 0;
}

.completed-description {
  color: #6b7280;
  margin: 0 0 1.5rem 0;
  line-height: 1.6;
}

.completion-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin: 1.5rem 0;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 0.75rem;
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
}

.stat-label {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.completion-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* Tips Section */
.tips-section {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.tips-title {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 1rem 0;
}

.tips-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.tip-item {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
}

.tip-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}

.tip-content {
  flex: 1;
}

.tip-title {
  font-weight: 600;
  color: var(--tg-theme-text-color, #1f2937);
  margin-bottom: 0.25rem;
}

.tip-description {
  font-size: 0.875rem;
  color: #6b7280;
  line-height: 1.4;
}

/* Button Styles */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 0.75rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
  border: none;
  cursor: pointer;
  font-size: 0.875rem;
}

.btn-primary {
  background: var(--tg-theme-button-color, #2481cc);
  color: white;
}

.btn-primary:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.btn-secondary {
  background: white;
  color: var(--tg-theme-text-color, #1f2937);
  border: 2px solid #e5e7eb;
}

.btn-secondary:hover {
  background: #f8fafc;
  border-color: #d1d5db;
  transform: translateY(-1px);
}

/* Responsive Design */
@media (max-width: 768px) {
  .questions-page {
    padding: 0.75rem;
  }
  
  .page-header {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .user-coins {
    align-self: flex-start;
  }
  
  .page-title {
    font-size: 1.5rem;
  }
  
  .questions-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .tips-grid {
    grid-template-columns: 1fr;
  }
  
  .completion-stats {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
  
  .completion-actions {
    flex-direction: column;
  }
  
  .completed-content {
    padding: 1.5rem;
    margin: 0.5rem;
  }
}

@media (max-width: 480px) {
  .questions-page {
    padding: 0.5rem;
  }
  
  .empty-state {
    padding: 2rem 1rem;
  }
  
  .empty-icon {
    font-size: 4rem;
  }
  
  .tip-item {
    flex-direction: column;
    text-align: center;
    gap: 0.5rem;
  }
  
  .progress-header {
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
  }
}
</style>