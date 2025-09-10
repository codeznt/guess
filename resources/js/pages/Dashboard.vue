<template>
  <div class="dashboard">
    <!-- Header Section -->
    <div class="dashboard-header">
      <div class="user-info">
        <div class="avatar-container">
          <img 
            v-if="user.avatar" 
            :src="user.avatar" 
            :alt="user.name"
            class="user-avatar"
          >
          <div v-else class="user-avatar-placeholder">
            {{ user.name.charAt(0).toUpperCase() }}
          </div>
        </div>
        <div class="user-details">
          <h1 class="user-name">{{ user.name }}</h1>
          <div class="user-coins">
            <span class="coin-icon">🪙</span>
            <span class="coins-amount">{{ formatNumber(user.daily_coins) }}</span>
            <span class="coins-label">coins</span>
          </div>
        </div>
      </div>
      
      <!-- Quick Stats -->
      <div class="quick-stats">
        <div class="stat-item">
          <div class="stat-value">{{ user.current_streak }}</div>
          <div class="stat-label">Streak</div>
          <div class="stat-multiplier">{{ user.streak_multiplier }}x</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">{{ userStats.accuracy_percentage }}%</div>
          <div class="stat-label">Accuracy</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">#{{ userStats.rank || '—' }}</div>
          <div class="stat-label">Rank</div>
        </div>
      </div>
    </div>

    <!-- Daily Questions Section -->
    <div class="daily-questions-section">
      <div class="section-header">
        <h2 class="section-title">
          🎯 Today's Questions
        </h2>
        <div class="questions-meta">
          <span class="questions-count">{{ questionsAnswered }} / {{ totalQuestions }}</span>
          <div class="time-remaining">
            ⏰ {{ timeUntilReset }}
          </div>
        </div>
      </div>

      <!-- Questions Grid -->
      <div class="questions-grid" v-if="questions.length > 0">
        <PredictionCard
          v-for="question in questions"
          :key="question.id"
          :question="question"
          :user-prediction="question.user_prediction"
          :user-coins="user.daily_coins"
          :streak-multiplier="user.streak_multiplier"
          @prediction-made="handlePredictionMade"
          class="question-card"
        />
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state">
        <div class="empty-icon">📅</div>
        <h3 class="empty-title">No Questions Today</h3>
        <p class="empty-description">
          New questions will be available soon. Check back later!
        </p>
      </div>

      <!-- All Questions Answered -->
      <div v-if="allQuestionsAnswered && questions.length > 0" class="completed-state">
        <div class="completed-icon">✅</div>
        <h3 class="completed-title">All Done for Today!</h3>
        <p class="completed-description">
          You've answered all today's questions. Come back tomorrow for new challenges!
        </p>
        <div class="completed-actions">
          <Link 
            :href="route('leaderboard')" 
            class="btn btn-secondary"
          >
            🏆 View Leaderboard
          </Link>
          <Link 
            :href="route('profile')" 
            class="btn btn-primary"
          >
            👤 View Stats
          </Link>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-activity" v-if="recentPredictions.length > 0">
      <h3 class="activity-title">
        📈 Recent Activity
      </h3>
      <div class="activity-list">
        <div 
          v-for="prediction in recentPredictions.slice(0, 3)" 
          :key="prediction.id"
          class="activity-item"
          :class="{ 
            'activity-correct': prediction.is_correct === true,
            'activity-incorrect': prediction.is_correct === false,
            'activity-pending': prediction.is_correct === null
          }"
        >
          <div class="activity-icon-container">
            <span 
              v-if="prediction.is_correct === true" 
              class="activity-result-icon correct"
            >
              ✅
            </span>
            <span 
              v-else-if="prediction.is_correct === false" 
              class="activity-result-icon incorrect"
            >
              ❌
            </span>
            <span 
              v-else 
              class="activity-result-icon pending"
            >
              ⏳
            </span>
          </div>
          <div class="activity-content">
            <div class="activity-question">{{ truncateText(prediction.question.title, 50) }}</div>
            <div class="activity-details">
              <span class="activity-choice">{{ prediction.choice === 'A' ? prediction.question.option_a : prediction.question.option_b }}</span>
              <span class="activity-bet">{{ formatNumber(prediction.bet_amount) }} coins</span>
              <span class="activity-time">{{ formatRelativeTime(prediction.created_at) }}</span>
            </div>
          </div>
          <div class="activity-result">
            <div v-if="prediction.is_correct === true" class="winnings positive">
              +{{ formatNumber(prediction.actual_winnings) }}
            </div>
            <div v-else-if="prediction.is_correct === false" class="winnings negative">
              -{{ formatNumber(prediction.bet_amount) }}
            </div>
            <div v-else class="winnings pending">
              Pending
            </div>
          </div>
        </div>
      </div>
      <Link 
        :href="route('profile')" 
        class="view-all-activity"
      >
        View All Activity →
      </Link>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <Link 
        :href="route('questions.daily')" 
        class="action-button primary"
        v-if="!allQuestionsAnswered"
      >
        <span class="action-icon">🎯</span>
        <span>Make Predictions</span>
      </Link>
      <Link 
        :href="route('leaderboard')" 
        class="action-button secondary"
      >
        <span class="action-icon">🏆</span>
        <span>Leaderboard</span>
      </Link>
      <Link 
        :href="route('profile')" 
        class="action-button secondary"
      >
        <span class="action-icon">👤</span>
        <span>Profile</span>
      </Link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PredictionCard from '@/components/PredictionCard.vue';
import { initializeTelegramMock } from '@/lib/telegram-mock';

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
  };
  resolution_time: string;
  user_prediction?: {
    choice: 'A' | 'B';
    bet_amount: number;
    potential_winnings: number;
    multiplier_applied: number;
  };
}

interface Prediction {
  id: number;
  choice: 'A' | 'B';
  bet_amount: number;
  potential_winnings: number;
  actual_winnings?: number;
  is_correct?: boolean | null;
  created_at: string;
  question: {
    id: number;
    title: string;
    option_a: string;
    option_b: string;
  };
}

interface UserStats {
  total_predictions: number;
  correct_predictions: number;
  accuracy_percentage: number;
  net_profit: number;
  rank?: number;
}

const props = defineProps<{
  user: User;
  questions: Question[];
  recentPredictions: Prediction[];
  userStats: UserStats;
  timeUntilReset: string;
}>();

// Reactive data
const telegram = ref<any>(null);

// Computed properties
const totalQuestions = computed(() => props.questions.length);

const questionsAnswered = computed(() => {
  return props.questions.filter(q => q.user_prediction).length;
});

const allQuestionsAnswered = computed(() => {
  return totalQuestions.value > 0 && questionsAnswered.value === totalQuestions.value;
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

const formatRelativeTime = (dateString: string): string => {
  const date = new Date(dateString);
  const now = new Date();
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
  
  if (diffInSeconds < 60) {
    return 'Just now';
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60);
    return `${minutes}m ago`;
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600);
    return `${hours}h ago`;
  } else {
    const days = Math.floor(diffInSeconds / 86400);
    return `${days}d ago`;
  }
};

const truncateText = (text: string, maxLength: number): string => {
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};

const handlePredictionMade = (data: any) => {
  // Provide haptic feedback
  if (telegram.value?.HapticFeedback) {
    telegram.value.HapticFeedback.notificationOccurred('success');
  }
  
  // Refresh the page to show updated data
  router.reload({ only: ['questions', 'user', 'recentPredictions', 'userStats'] });
};

// Lifecycle
onMounted(() => {
  // Initialize Telegram WebApp
  telegram.value = initializeTelegramMock();
  
  if (telegram.value) {
    telegram.value.ready();
    telegram.value.expand();
    
    // Set theme
    document.documentElement.style.setProperty('--tg-theme-bg-color', telegram.value.themeParams.bg_color || '#ffffff');
    document.documentElement.style.setProperty('--tg-theme-text-color', telegram.value.themeParams.text_color || '#000000');
    document.documentElement.style.setProperty('--tg-theme-button-color', telegram.value.themeParams.button_color || '#2481cc');
  }
});
</script>

<style scoped>
.dashboard {
  min-height: 100vh;
  background: linear-gradient(to bottom, var(--tg-theme-bg-color, #f8fafc), #ffffff);
  padding: 1rem;
  padding-bottom: 2rem;
}

.dashboard-header {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.user-avatar {
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--tg-theme-button-color, #2481cc);
}

.user-avatar-placeholder {
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  background: var(--tg-theme-button-color, #2481cc);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: bold;
}

.user-details {
  flex: 1;
}

.user-name {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 0.5rem 0;
}

.user-coins {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #f59e0b;
  font-weight: 600;
}

.coin-icon {
  font-size: 1.25rem;
}

.coins-amount {
  font-size: 1.25rem;
}

.coins-label {
  font-size: 0.875rem;
  opacity: 0.8;
}

.quick-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.stat-item {
  text-align: center;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 0.5rem;
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

.stat-multiplier {
  font-size: 0.875rem;
  color: var(--tg-theme-button-color, #2481cc);
  font-weight: 600;
  margin-top: 0.25rem;
}

.daily-questions-section {
  margin-bottom: 2rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.section-title {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0;
}

.questions-meta {
  display: flex;
  align-items: center;
  gap: 1rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.questions-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

.empty-state,
.completed-state {
  text-align: center;
  padding: 3rem 1.5rem;
  background: white;
  border-radius: 1rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.empty-icon,
.completed-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.empty-title,
.completed-title {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 0.5rem 0;
}

.empty-description,
.completed-description {
  color: #6b7280;
  margin: 0 0 1.5rem 0;
}

.completed-actions {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
  flex-wrap: wrap;
}

.recent-activity {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.activity-title {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 1rem 0;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  border-radius: 0.5rem;
  transition: background-color 0.2s;
  margin-bottom: 0.75rem;
}

.activity-item:hover {
  background: #f8fafc;
}

.activity-icon-container {
  flex-shrink: 0;
}

.activity-result-icon {
  font-size: 1.5rem;
}

.activity-content {
  flex: 1;
  min-width: 0;
}

.activity-question {
  font-weight: 600;
  color: var(--tg-theme-text-color, #1f2937);
  margin-bottom: 0.25rem;
}

.activity-details {
  display: flex;
  gap: 1rem;
  font-size: 0.875rem;
  color: #6b7280;
  flex-wrap: wrap;
}

.activity-result {
  flex-shrink: 0;
  font-weight: 600;
}

.winnings.positive {
  color: #059669;
}

.winnings.negative {
  color: #dc2626;
}

.winnings.pending {
  color: #d97706;
}

.view-all-activity {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 1rem;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 0.5rem;
  color: var(--tg-theme-button-color, #2481cc);
  font-weight: 600;
  text-decoration: none;
  transition: background-color 0.2s;
}

.view-all-activity:hover {
  background: #e2e8f0;
}

.quick-actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 1rem;
}

.action-button {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem;
  border-radius: 0.75rem;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s;
}

.action-button.primary {
  background: var(--tg-theme-button-color, #2481cc);
  color: white;
}

.action-button.primary:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.action-button.secondary {
  background: white;
  color: var(--tg-theme-text-color, #1f2937);
  border: 1px solid #e5e7eb;
}

.action-button.secondary:hover {
  background: #f8fafc;
  transform: translateY(-1px);
}

.action-icon {
  font-size: 1.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
  border: none;
  cursor: pointer;
}

.btn-primary {
  background: var(--tg-theme-button-color, #2481cc);
  color: white;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.btn-secondary {
  background: white;
  color: var(--tg-theme-text-color, #1f2937);
  border: 1px solid #e5e7eb;
}

.btn-secondary:hover {
  background: #f8fafc;
}

@media (max-width: 640px) {
  .dashboard {
    padding: 0.5rem;
  }
  
  .dashboard-header {
    padding: 1rem;
  }
  
  .quick-stats {
    gap: 0.5rem;
  }
  
  .stat-item {
    padding: 0.75rem 0.5rem;
  }
  
  .questions-grid {
    grid-template-columns: 1fr;
  }
  
  .activity-details {
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .completed-actions {
    flex-direction: column;
  }
  
  .quick-actions {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
