<template>
  <div class="profile-page">
    <!-- Header -->
    <div class="profile-header">
      <div class="profile-avatar">
        <div class="avatar-circle">
          {{ user.first_name?.charAt(0) || 'U' }}
        </div>
      </div>
      <div class="profile-info">
        <h1 class="profile-name">{{ user.first_name }} {{ user.last_name }}</h1>
        <p class="profile-username">@{{ user.username || 'user' }}</p>
        <div class="profile-level">
          <span class="level-badge">Level {{ userLevel }}</span>
          <span class="experience">{{ user.total_points || 0 }} XP</span>
        </div>
      </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="quick-stats">
      <div class="stat-card">
        <div class="stat-icon">🏆</div>
        <div class="stat-value">{{ user.total_winnings || 0 }}</div>
        <div class="stat-label">Total Coins Won</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🔥</div>
        <div class="stat-value">{{ user.current_streak || 0 }}</div>
        <div class="stat-label">Current Streak</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value">{{ accuracyPercentage }}%</div>
        <div class="stat-label">Accuracy</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value">{{ user.predictions_made || 0 }}</div>
        <div class="stat-label">Predictions Made</div>
      </div>
    </div>

    <!-- Detailed Statistics -->
    <div class="stats-section">
      <h2 class="section-title">Performance Statistics</h2>
      
      <!-- Streak Information -->
      <div class="stats-card">
        <h3 class="card-title">Streak Records</h3>
        <div class="streak-stats">
          <div class="streak-item">
            <span class="streak-label">Current Streak</span>
            <div class="streak-display">
              <span class="streak-number">{{ user.current_streak || 0 }}</span>
              <span class="streak-emoji">🔥</span>
            </div>
          </div>
          <div class="streak-item">
            <span class="streak-label">Best Streak</span>
            <div class="streak-display">
              <span class="streak-number">{{ user.best_streak || 0 }}</span>
              <span class="streak-emoji">⭐</span>
            </div>
          </div>
          <div class="streak-item">
            <span class="streak-label">Streak Potential</span>
            <div class="streak-display">
              <span class="streak-number">{{ streakPotential }}</span>
              <span class="streak-emoji">🚀</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Prediction Breakdown -->
      <div class="stats-card">
        <h3 class="card-title">Prediction Breakdown</h3>
        <div class="prediction-stats">
          <div class="prediction-item">
            <span class="prediction-label">Total Predictions</span>
            <span class="prediction-value">{{ user.predictions_made || 0 }}</span>
          </div>
          <div class="prediction-item">
            <span class="prediction-label">Correct Predictions</span>
            <span class="prediction-value correct">{{ correctPredictions }}</span>
          </div>
          <div class="prediction-item">
            <span class="prediction-label">Accuracy Rate</span>
            <span class="prediction-value">{{ accuracyPercentage }}%</span>
          </div>
          <div class="prediction-item">
            <span class="prediction-label">Favorite Category</span>
            <span class="prediction-value">{{ favoriteCategory }}</span>
          </div>
        </div>
      </div>

      <!-- Earnings Summary -->
      <div class="stats-card">
        <h3 class="card-title">Earnings Summary</h3>
        <div class="earnings-stats">
          <div class="earnings-item">
            <span class="earnings-label">Total Winnings</span>
            <span class="earnings-value">{{ user.total_winnings || 0 }} coins</span>
          </div>
          <div class="earnings-item">
            <span class="earnings-label">Total Wagered</span>
            <span class="earnings-value">{{ user.total_wagered || 0 }} coins</span>
          </div>
          <div class="earnings-item">
            <span class="earnings-label">Net Profit</span>
            <span class="earnings-value" :class="netProfitClass">{{ netProfit }} coins</span>
          </div>
          <div class="earnings-item">
            <span class="earnings-label">ROI</span>
            <span class="earnings-value" :class="roiClass">{{ roi }}%</span>
          </div>
        </div>
      </div>

      <!-- Achievements -->
      <div class="stats-card">
        <h3 class="card-title">Achievements</h3>
        <div class="achievements-grid">
          <div 
            v-for="achievement in achievements" 
            :key="achievement.id"
            class="achievement-badge"
            :class="{ earned: achievement.earned }"
          >
            <div class="achievement-icon">{{ achievement.emoji }}</div>
            <div class="achievement-info">
              <div class="achievement-name">{{ achievement.name }}</div>
              <div class="achievement-description">{{ achievement.description }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="profile-actions">
      <button 
        @click="shareStats" 
        class="action-button primary"
        :disabled="!telegram"
      >
        <span class="button-icon">📤</span>
        Share Stats
      </button>
      <Link 
        href="/leaderboard" 
        class="action-button secondary"
      >
        <span class="button-icon">🏆</span>
        View Rankings
      </Link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { initializeTelegramMock } from '@/lib/telegram-mock';

// Props
interface Props {
  user: {
    id: number;
    telegram_id: string;
    username?: string;
    first_name: string;
    last_name?: string;
    daily_coins: number;
    current_streak: number;
    best_streak: number;
    total_points?: number;
    total_winnings?: number;
    total_wagered?: number;
    predictions_made?: number;
    correct_predictions?: number;
  };
  achievements: Array<{
    id: number;
    name: string;
    description: string;
    emoji: string;
    earned: boolean;
    earned_at?: string;
  }>;
  categoryStats?: Array<{
    category: string;
    predictions: number;
    accuracy: number;
  }>;
}

const props = defineProps<Props>();

// Telegram WebApp
const telegram = ref<any>(null);

// Computed properties
const userLevel = computed(() => {
  const points = props.user.total_points || 0;
  return Math.floor(points / 1000) + 1;
});

const correctPredictions = computed(() => {
  return props.user.correct_predictions || 0;
});

const accuracyPercentage = computed(() => {
  const total = props.user.predictions_made || 0;
  const correct = correctPredictions.value;
  return total > 0 ? Math.round((correct / total) * 100) : 0;
});

const favoriteCategory = computed(() => {
  if (!props.categoryStats || props.categoryStats.length === 0) {
    return 'None';
  }
  
  const topCategory = props.categoryStats.reduce((prev, current) => {
    return prev.predictions > current.predictions ? prev : current;
  });
  
  return topCategory.category;
});

const netProfit = computed(() => {
  const winnings = props.user.total_winnings || 0;
  const wagered = props.user.total_wagered || 0;
  return winnings - wagered;
});

const netProfitClass = computed(() => {
  const profit = netProfit.value;
  return profit > 0 ? 'profit' : profit < 0 ? 'loss' : 'neutral';
});

const roi = computed(() => {
  const wagered = props.user.total_wagered || 0;
  if (wagered === 0) return 0;
  return Math.round((netProfit.value / wagered) * 100);
});

const roiClass = computed(() => {
  const roiValue = roi.value;
  return roiValue > 0 ? 'profit' : roiValue < 0 ? 'loss' : 'neutral';
});

const streakPotential = computed(() => {
  const current = props.user.current_streak || 0;
  const best = props.user.best_streak || 0;
  return Math.max(best + 1, current + 3);
});

// Methods
const shareStats = () => {
  if (!telegram.value) return;
  
  const stats = `🎯 My Prediction Game Stats:
🏆 ${props.user.total_winnings || 0} coins won
🔥 ${props.user.current_streak || 0} day streak
📊 ${accuracyPercentage.value}% accuracy
📅 ${props.user.predictions_made || 0} predictions made

Join me in the daily prediction challenge!`;

  if (telegram.value.shareToStory) {
    telegram.value.shareToStory(window.location.origin, {
      text: stats
    });
  } else {
    // Fallback to sharing via main button
    navigator.share?.({
      title: 'My Prediction Game Stats',
      text: stats,
      url: window.location.origin
    });
  }
  
  if (telegram.value.HapticFeedback) {
    telegram.value.HapticFeedback.notificationOccurred('success');
  }
};

// Lifecycle
onMounted(() => {
  telegram.value = initializeTelegramMock();
  
  if (telegram.value) {
    telegram.value.ready();
    telegram.value.expand();
    
    // Set header color
    if (telegram.value.setHeaderColor) {
      telegram.value.setHeaderColor('#2563eb');
    }
    
    // Hide main button on this page
    if (telegram.value.MainButton) {
      telegram.value.MainButton.hide();
    }
  }
});
</script>

<style scoped>
.profile-page {
  min-height: 100vh;
  background: var(--tg-color-bg, #f8fafc);
  padding: 1rem;
}

/* Header */
.profile-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  background: var(--tg-color-bg-secondary, white);
  border-radius: 1rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.profile-avatar {
  flex-shrink: 0;
}

.avatar-circle {
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
  font-weight: bold;
}

.profile-info {
  flex: 1;
}

.profile-name {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
  margin: 0 0 0.25rem 0;
}

.profile-username {
  color: var(--tg-color-hint, #6b7280);
  margin: 0 0 0.5rem 0;
}

.profile-level {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.level-badge {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 1rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.experience {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
}

/* Quick Stats */
.quick-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: var(--tg-color-bg-secondary, white);
  padding: 1.25rem;
  border-radius: 1rem;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.875rem;
  color: var(--tg-color-hint, #6b7280);
}

/* Stats Section */
.stats-section {
  margin-bottom: 2rem;
}

.section-title {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 1rem;
}

.stats-card {
  background: var(--tg-color-bg-secondary, white);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.card-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 1rem;
}

/* Streak Stats */
.streak-stats {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.streak-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--tg-color-bg, #f8fafc);
  border-radius: 0.5rem;
}

.streak-label {
  color: var(--tg-color-text, #1f2937);
  font-weight: 500;
}

.streak-display {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.streak-number {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
}

.streak-emoji {
  font-size: 1.25rem;
}

/* Prediction Stats */
.prediction-stats {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.prediction-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--tg-color-bg, #f1f5f9);
}

.prediction-item:last-child {
  border-bottom: none;
}

.prediction-label {
  color: var(--tg-color-hint, #6b7280);
}

.prediction-value {
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
}

.prediction-value.correct {
  color: #10b981;
}

/* Earnings Stats */
.earnings-stats {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.earnings-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--tg-color-bg, #f1f5f9);
}

.earnings-item:last-child {
  border-bottom: none;
}

.earnings-label {
  color: var(--tg-color-hint, #6b7280);
}

.earnings-value {
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
}

.earnings-value.profit {
  color: #10b981;
}

.earnings-value.loss {
  color: #ef4444;
}

.earnings-value.neutral {
  color: var(--tg-color-hint, #6b7280);
}

/* Achievements */
.achievements-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

.achievement-badge {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border-radius: 0.75rem;
  background: var(--tg-color-bg, #f8fafc);
  opacity: 0.5;
  transition: all 0.2s ease;
}

.achievement-badge.earned {
  opacity: 1;
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border: 2px solid #f59e0b;
}

.achievement-icon {
  font-size: 2rem;
  flex-shrink: 0;
}

.achievement-info {
  flex: 1;
}

.achievement-name {
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 0.25rem;
}

.achievement-description {
  font-size: 0.875rem;
  color: var(--tg-color-hint, #6b7280);
}

/* Actions */
.profile-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 2rem;
}

.action-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 1rem;
  border: none;
  border-radius: 0.75rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
  cursor: pointer;
}

.action-button.primary {
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
}

.action-button.primary:hover:not(:disabled) {
  background: #1d4ed8;
}

.action-button.primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-button.secondary {
  background: var(--tg-color-bg-secondary, white);
  color: var(--tg-color-text, #1f2937);
  border: 2px solid var(--tg-color-button, #2563eb);
}

.action-button.secondary:hover {
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
}

.button-icon {
  font-size: 1.125rem;
}

@media (max-width: 640px) {
  .profile-page {
    padding: 0.75rem;
  }
  
  .profile-header {
    padding: 1rem;
  }
  
  .profile-name {
    font-size: 1.25rem;
  }
  
  .quick-stats {
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
  }
  
  .stat-card {
    padding: 1rem;
  }
  
  .stat-value {
    font-size: 1.25rem;
  }
  
  .stats-card {
    padding: 1rem;
  }
  
  .profile-actions {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
}
</style>