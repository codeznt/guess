<template>
  <div class="dashboard">
    <!-- Header Section -->
    <Card class="dashboard-header">
      <CardContent class="pt-6">
        <div class="user-info">
          <Avatar class="user-avatar">
            <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
            <AvatarFallback>{{ user?.name ? user.name.charAt(0).toUpperCase() : '?' }}</AvatarFallback>
          </Avatar>
          <div class="user-details">
            <h1 class="user-name">{{ user.name }}</h1>
            <div class="user-coins">
              <IconCoins class="coin-icon h-5 w-5" />
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
      </CardContent>
    </Card>

    <!-- Daily Questions Section -->
    <div class="daily-questions-section">
      <div class="section-header">
        <h2 class="section-title flex items-center gap-2">
          <IconTarget class="h-6 w-6" />
          Today's Questions
        </h2>
        <div class="questions-meta">
          <span class="questions-count">{{ questionsAnswered }} / {{ totalQuestions }}</span>
          <div class="time-remaining flex items-center gap-1">
            <IconClock class="h-4 w-4" />
            {{ timeUntilReset }}
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
      <Card v-else class="empty-state">
        <CardContent class="p-12 text-center">
          <IconCalendar class="h-16 w-16 mx-auto mb-4 text-muted-foreground" />
          <h3 class="empty-title">No Questions Today</h3>
          <p class="empty-description">
            New questions will be available soon. Check back later!
          </p>
        </CardContent>
      </Card>

      <!-- All Questions Answered -->
      <Card v-if="allQuestionsAnswered && questions.length > 0" class="completed-state">
        <CardContent class="p-12 text-center">
          <IconCheck class="h-16 w-16 mx-auto mb-4 text-green-500" />
          <h3 class="completed-title">All Done for Today!</h3>
          <p class="completed-description">
            You've answered all today's questions. Come back tomorrow for new challenges!
          </p>
          <div class="completed-actions">
            <Button variant="outline" as-child>
              <Link :href="leaderboard.index.url()">
                <IconTrophy class="h-4 w-4 mr-2" />
                View Leaderboard
              </Link>
            </Button>
            <Button as-child>
              <Link :href="profile.show.url()">
                <IconUser class="h-4 w-4 mr-2" />
                View Stats
              </Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Recent Activity -->
    <Card class="recent-activity" v-if="recentPredictions.length > 0">
      <CardContent class="pt-6">
        <h3 class="activity-title flex items-center gap-2">
          <IconTrendingUp class="h-6 w-6" />
          Recent Activity
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
              <IconCheck 
                v-if="prediction.is_correct === true" 
                class="h-6 w-6 text-green-500"
              />
              <IconX 
                v-else-if="prediction.is_correct === false" 
                class="h-6 w-6 text-red-500"
              />
              <IconClock 
                v-else 
                class="h-6 w-6 text-orange-500"
              />
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
          :href="profile.show.url()" 
          class="view-all-activity flex items-center justify-center gap-2"
        >
          View All Activity
          <IconArrowRight class="h-4 w-4" />
        </Link>
      </CardContent>
    </Card>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <Button as-child class="action-button" v-if="!allQuestionsAnswered">
        <Link :href="questionsRoutes.daily.url()">
          <IconTarget class="h-6 w-6 mb-2" />
          Make Predictions
        </Link>
      </Button>
      <Button variant="outline" as-child class="action-button">
        <Link :href="leaderboard.index.url()">
          <IconTrophy class="h-6 w-6 mb-2" />
          Leaderboard
        </Link>
      </Button>
      <Button variant="outline" as-child class="action-button">
        <Link :href="profile.show.url()">
          <IconUser class="h-6 w-6 mb-2" />
          Profile
        </Link>
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PredictionCard from '@/components/PredictionCard.vue';
import { initializeTelegramMock } from '@/lib/telegram-mock';
// Import shadcn-vue components
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
// Import Tabler icons
import { IconTarget, IconCoins, IconClock, IconCalendar, IconCheck, IconTrophy, IconUser, IconTrendingUp, IconX, IconArrowRight } from '@tabler/icons-vue';
// Import Wayfinder routes
import { dashboard } from '@/routes';
import leaderboard from '@/routes/leaderboard';
import profile from '@/routes/profile';
import questionsRoutes from '@/routes/questions';

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

interface Prediction {
  id: number;
  choice: 'A' | 'B';
  bet_amount: number;
  potential_winnings: number;
  actual_winnings?: number;
  is_correct?: boolean;
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
const formatNumber = (num: number | undefined): string => {
  if (!num) return '0';
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
  margin-bottom: 1.5rem;
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
  border: 3px solid var(--tg-theme-button-color, #2481cc);
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
  color: #f59e0b;
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
  margin-bottom: 1.5rem;
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
  margin-bottom: 1.5rem;
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
  min-height: 5rem;
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
