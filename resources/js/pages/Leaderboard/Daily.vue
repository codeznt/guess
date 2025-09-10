<template>
  <div class="leaderboard-page">
    <!-- Header -->
    <div class="page-header">
      <h1 class="page-title">🏆 Leaderboard</h1>
      <p class="page-subtitle">See how you rank against other players</p>
      
      <!-- Period Tabs -->
      <div class="period-tabs">
        <button 
          v-for="period in periods" 
          :key="period.value"
          @click="activePeriod = period.value"
          class="period-tab"
          :class="{ 'active': activePeriod === period.value }"
        >
          {{ period.label }}
        </button>
      </div>
    </div>

    <!-- User Position Card -->
    <div class="user-position-card" v-if="userPosition">
      <div class="position-header">
        <span class="position-label">Your Position</span>
        <span class="period-info">{{ currentPeriodLabel }}</span>
      </div>
      <div class="position-content">
        <div class="user-rank">
          <div class="rank-number">#{{ userPosition.rank }}</div>
          <div class="rank-label">Rank</div>
        </div>
        <div class="user-info">
          <div class="user-avatar">
            <img 
              v-if="user.avatar" 
              :src="user.avatar" 
              :alt="user.name"
            >
            <div v-else class="avatar-placeholder">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
          </div>
          <div class="user-details">
            <div class="user-name">{{ user.name }}</div>
            <div class="user-stats">
              {{ userPosition.score }} points • {{ userPosition.accuracy }}% accuracy
            </div>
          </div>
        </div>
        <div class="position-change" v-if="userPosition.change">
          <div 
            class="change-indicator"
            :class="{ 
              'positive': userPosition.change > 0,
              'negative': userPosition.change < 0 
            }"
          >
            <span v-if="userPosition.change > 0">↗️</span>
            <span v-else-if="userPosition.change < 0">↘️</span>
            <span v-else>➡️</span>
            {{ Math.abs(userPosition.change) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Leaderboard Table -->
    <div class="leaderboard-container">
      <LeaderboardTable 
        :entries="rankings"
        :current-user-id="user.id"
        :user-position="userPosition"
        :is-loading="loading"
        @load-more="loadMore"
      />
    </div>

    <!-- Stats Summary -->
    <div class="stats-summary">
      <h3 class="stats-title">📊 Competition Stats</h3>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">👥</div>
          <div class="stat-content">
            <div class="stat-value">{{ formatNumber(stats.total_players) }}</div>
            <div class="stat-label">Total Players</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🎯</div>
          <div class="stat-content">
            <div class="stat-value">{{ formatNumber(stats.total_predictions) }}</div>
            <div class="stat-label">Predictions Made</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🪙</div>
          <div class="stat-content">
            <div class="stat-value">{{ formatNumber(stats.total_winnings) }}</div>
            <div class="stat-label">Coins Won</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📈</div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.average_accuracy }}%</div>
            <div class="stat-label">Avg Accuracy</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import LeaderboardTable from '@/components/LeaderboardTable.vue';
import { initializeTelegramMock } from '@/lib/telegram-mock';
// Import Wayfinder routes
import { dashboard } from '@/routes';

// Props
interface User {
  id: number;
  name: string;
  avatar?: string;
}

interface UserPosition {
  rank: number;
  score: number;
  accuracy: number;
  change?: number;
}

interface LeaderboardEntry {
  user_id: number;
  rank: number;
  username?: string;
  first_name: string;
  last_name?: string;
  total_winnings: number;
  predictions_made: number;
  accuracy_percentage: number;
  current_streak: number;
  avatar_url?: string;
  trend?: 'up' | 'down' | 'same';
}

interface Stats {
  total_players: number;
  total_predictions: number;
  total_winnings: number;
  average_accuracy: number;
}

const props = defineProps<{
  user: User;
  rankings: LeaderboardEntry[];
  userPosition?: UserPosition;
  stats: Stats;
  period?: 'daily' | 'weekly' | 'monthly';
}>();

// Reactive data
const telegram = ref<any>(null);
const activePeriod = ref<'daily' | 'weekly' | 'monthly'>(props.period || 'daily');
const loading = ref(false);

// Period configuration
const periods = [
  { value: 'daily' as const, label: 'Daily' },
  { value: 'weekly' as const, label: 'Weekly' },
  { value: 'monthly' as const, label: 'Monthly' },
];

// Computed properties
const currentPeriodLabel = computed(() => {
  const period = periods.find(p => p.value === activePeriod.value);
  return period?.label || 'Daily';
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

const loadMore = () => {
  // Implement pagination loading
  console.log('Load more rankings');
};

// Watch for period changes
watch(activePeriod, (newPeriod) => {
  loading.value = true;
  router.reload({ 
    data: { period: newPeriod },
    only: ['rankings', 'userPosition', 'stats'],
    onFinish: () => {
      loading.value = false;
    }
  });
});

// Lifecycle
onMounted(() => {
  // Initialize Telegram WebApp
  telegram.value = initializeTelegramMock();
  
  if (telegram.value) {
    telegram.value.ready();
    telegram.value.expand();
    
    // Set back button
    telegram.value.BackButton.onClick(() => {
      router.visit(dashboard.url());
    });
    telegram.value.BackButton.show();
    
    // Set theme
    document.documentElement.style.setProperty('--tg-theme-bg-color', telegram.value.themeParams.bg_color || '#ffffff');
    document.documentElement.style.setProperty('--tg-theme-text-color', telegram.value.themeParams.text_color || '#000000');
    document.documentElement.style.setProperty('--tg-theme-button-color', telegram.value.themeParams.button_color || '#2481cc');
  }
});
</script>

<style scoped>
.leaderboard-page {
  min-height: 100vh;
  background: linear-gradient(to bottom, var(--tg-theme-bg-color, #f8fafc), #ffffff);
  padding: 1rem;
  padding-bottom: 2rem;
}

.page-header {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.page-title {
  font-size: 1.75rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 0.5rem 0;
}

.page-subtitle {
  color: #6b7280;
  margin: 0 0 1.5rem 0;
}

.period-tabs {
  display: flex;
  gap: 0.5rem;
  background: #f8fafc;
  padding: 0.25rem;
  border-radius: 0.75rem;
}

.period-tab {
  flex: 1;
  padding: 0.75rem 1rem;
  border: none;
  background: transparent;
  border-radius: 0.5rem;
  font-weight: 600;
  color: #6b7280;
  transition: all 0.2s;
  cursor: pointer;
}

.period-tab.active {
  background: white;
  color: var(--tg-theme-text-color, #1f2937);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.period-tab:not(.active):hover {
  color: var(--tg-theme-text-color, #1f2937);
}

.user-position-card {
  background: linear-gradient(135deg, var(--tg-theme-button-color, #2481cc), #1d4ed8);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  color: white;
}

.position-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.position-label {
  font-weight: 600;
  opacity: 0.9;
}

.period-info {
  font-size: 0.875rem;
  opacity: 0.8;
}

.position-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-rank {
  text-align: center;
}

.rank-number {
  font-size: 2rem;
  font-weight: bold;
  line-height: 1;
}

.rank-label {
  font-size: 0.75rem;
  opacity: 0.8;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.user-avatar {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid rgba(255, 255, 255, 0.3);
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-placeholder {
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  font-weight: bold;
}

.user-details {
  flex: 1;
}

.user-name {
  font-weight: bold;
  font-size: 1.125rem;
  margin-bottom: 0.25rem;
}

.user-stats {
  font-size: 0.875rem;
  opacity: 0.9;
}

.position-change {
  text-align: center;
}

.change-indicator {
  font-size: 0.875rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.change-indicator.positive {
  color: #10b981;
}

.change-indicator.negative {
  color: #ef4444;
}

.leaderboard-container {
  margin-bottom: 2rem;
}

.stats-summary {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.stats-title {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
  margin: 0 0 1rem 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
}

.stat-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-theme-text-color, #1f2937);
}

.stat-label {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 0.125rem;
}

@media (max-width: 768px) {
  .leaderboard-page {
    padding: 0.75rem;
  }
  
  .page-header {
    padding: 1rem;
  }
  
  .page-title {
    font-size: 1.5rem;
  }
  
  .position-content {
    flex-wrap: wrap;
    gap: 0.75rem;
  }
  
  .user-info {
    order: -1;
    width: 100%;
  }
  
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .leaderboard-page {
    padding: 0.5rem;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-card {
    padding: 0.75rem;
  }
  
  .user-position-card {
    padding: 1rem;
  }
}
</style>