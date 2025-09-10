<template>
  <div class="profile-page">
    <!-- Header -->
    <Card class="profile-header mb-6">
      <CardContent class="pt-6">
        <div class="flex items-center gap-4">
          <Avatar class="profile-avatar h-16 w-16">
            <AvatarFallback class="text-2xl font-bold">
              {{ user.first_name?.charAt(0) || 'U' }}
            </AvatarFallback>
          </Avatar>
          <div class="profile-info flex-1">
            <CardTitle class="profile-name">{{ user.first_name }} {{ user.last_name }}</CardTitle>
            <CardDescription class="profile-username">@{{ user.username || 'user' }}</CardDescription>
            <div class="profile-level flex items-center gap-3 mt-2">
              <Badge class="level-badge">Level {{ userLevel }}</Badge>
              <span class="experience text-sm text-muted-foreground">{{ user.total_points || 0 }} XP</span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Quick Stats Grid -->
    <div class="quick-stats grid grid-cols-2 gap-4 mb-6">
      <Card class="stat-card">
        <CardContent class="text-center pt-6">
          <IconTrophy class="stat-icon h-8 w-8 mx-auto mb-2 text-yellow-500" />
          <div class="stat-value text-2xl font-bold mb-1">{{ user.total_winnings || 0 }}</div>
          <div class="stat-label text-sm text-muted-foreground">Total Coins Won</div>
        </CardContent>
      </Card>
      <Card class="stat-card">
        <CardContent class="text-center pt-6">
          <IconFlame class="stat-icon h-8 w-8 mx-auto mb-2 text-red-500" />
          <div class="stat-value text-2xl font-bold mb-1">{{ user.current_streak || 0 }}</div>
          <div class="stat-label text-sm text-muted-foreground">Current Streak</div>
        </CardContent>
      </Card>
      <Card class="stat-card">
        <CardContent class="text-center pt-6">
          <IconTrendingUp class="stat-icon h-8 w-8 mx-auto mb-2 text-green-500" />
          <div class="stat-value text-2xl font-bold mb-1">{{ accuracyPercentage }}%</div>
          <div class="stat-label text-sm text-muted-foreground">Accuracy</div>
        </CardContent>
      </Card>
      <Card class="stat-card">
        <CardContent class="text-center pt-6">
          <IconCalendar class="stat-icon h-8 w-8 mx-auto mb-2 text-blue-500" />
          <div class="stat-value text-2xl font-bold mb-1">{{ user.predictions_made || 0 }}</div>
          <div class="stat-label text-sm text-muted-foreground">Predictions Made</div>
        </CardContent>
      </Card>
    </div>

    <!-- Detailed Statistics -->
    <div class="stats-section">
      <h2 class="section-title text-xl font-bold mb-4">Performance Statistics</h2>
      
      <!-- Streak Information -->
      <Card class="stats-card mb-4">
        <CardHeader>
          <CardTitle class="card-title">Streak Records</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="streak-stats space-y-4">
            <div class="streak-item flex justify-between items-center p-3 bg-muted rounded-lg">
              <span class="streak-label font-medium">Current Streak</span>
              <div class="streak-display flex items-center gap-2">
                <span class="streak-number text-xl font-bold">{{ user.current_streak || 0 }}</span>
                <IconFlame class="h-5 w-5 text-red-500" />
              </div>
            </div>
            <div class="streak-item flex justify-between items-center p-3 bg-muted rounded-lg">
              <span class="streak-label font-medium">Best Streak</span>
              <div class="streak-display flex items-center gap-2">
                <span class="streak-number text-xl font-bold">{{ user.best_streak || 0 }}</span>
                <IconStar class="h-5 w-5 text-yellow-500" />
              </div>
            </div>
            <div class="streak-item flex justify-between items-center p-3 bg-muted rounded-lg">
              <span class="streak-label font-medium">Streak Potential</span>
              <div class="streak-display flex items-center gap-2">
                <span class="streak-number text-xl font-bold">{{ streakPotential }}</span>
                <IconRocket class="h-5 w-5 text-purple-500" />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Prediction Breakdown -->
      <Card class="stats-card mb-4">
        <CardHeader>
          <CardTitle class="card-title">Prediction Breakdown</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="prediction-stats space-y-3">
            <div class="prediction-item flex justify-between items-center py-2 border-b">
              <span class="prediction-label text-muted-foreground">Total Predictions</span>
              <span class="prediction-value font-semibold">{{ user.predictions_made || 0 }}</span>
            </div>
            <div class="prediction-item flex justify-between items-center py-2 border-b">
              <span class="prediction-label text-muted-foreground">Correct Predictions</span>
              <span class="prediction-value correct font-semibold text-green-600">{{ correctPredictions }}</span>
            </div>
            <div class="prediction-item flex justify-between items-center py-2 border-b">
              <span class="prediction-label text-muted-foreground">Accuracy Rate</span>
              <span class="prediction-value font-semibold">{{ accuracyPercentage }}%</span>
            </div>
            <div class="prediction-item flex justify-between items-center py-2">
              <span class="prediction-label text-muted-foreground">Favorite Category</span>
              <span class="prediction-value font-semibold">{{ favoriteCategory }}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Earnings Summary -->
      <Card class="stats-card mb-4">
        <CardHeader>
          <CardTitle class="card-title">Earnings Summary</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="earnings-stats space-y-3">
            <div class="earnings-item flex justify-between items-center py-2 border-b">
              <span class="earnings-label text-muted-foreground">Total Winnings</span>
              <span class="earnings-value font-semibold">{{ user.total_winnings || 0 }} coins</span>
            </div>
            <div class="earnings-item flex justify-between items-center py-2 border-b">
              <span class="earnings-label text-muted-foreground">Total Wagered</span>
              <span class="earnings-value font-semibold">{{ user.total_wagered || 0 }} coins</span>
            </div>
            <div class="earnings-item flex justify-between items-center py-2 border-b">
              <span class="earnings-label text-muted-foreground">Net Profit</span>
              <span class="earnings-value font-semibold" :class="{
                'text-green-600': netProfitClass === 'profit',
                'text-red-600': netProfitClass === 'loss',
                'text-muted-foreground': netProfitClass === 'neutral'
              }">{{ netProfit }} coins</span>
            </div>
            <div class="earnings-item flex justify-between items-center py-2">
              <span class="earnings-label text-muted-foreground">ROI</span>
              <span class="earnings-value font-semibold" :class="{
                'text-green-600': roiClass === 'profit',
                'text-red-600': roiClass === 'loss',
                'text-muted-foreground': roiClass === 'neutral'
              }">{{ roi }}%</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Achievements -->
      <Card class="stats-card mb-4">
        <CardHeader>
          <CardTitle class="card-title">Achievements</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="achievements-grid space-y-3">
            <div 
              v-for="achievement in achievements" 
              :key="achievement.id"
              class="achievement-badge flex items-center gap-4 p-4 rounded-lg transition-all"
              :class="{ 
                'bg-yellow-50 border-2 border-yellow-200 opacity-100': achievement.earned,
                'bg-muted opacity-50': !achievement.earned
              }"
            >
              <div class="achievement-icon text-2xl flex-shrink-0">{{ achievement.emoji }}</div>
              <div class="achievement-info flex-1">
                <div class="achievement-name font-semibold mb-1">{{ achievement.name }}</div>
                <div class="achievement-description text-sm text-muted-foreground">{{ achievement.description }}</div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Actions -->
    <div class="profile-actions grid grid-cols-2 gap-4 mt-8">
      <Button 
        @click="shareStats" 
        :disabled="!telegram"
        class="action-button"
      >
        <IconShare class="h-5 w-5 mr-2" />
        Share Stats
      </Button>
      <Button variant="outline" as-child class="action-button">
        <Link href="/leaderboard">
          <IconTrophy class="h-5 w-5 mr-2" />
          View Rankings
        </Link>
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { initializeTelegramMock } from '@/lib/telegram-mock';
// Import shadcn-vue components
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
// Import Tabler icons
import { IconTrophy, IconFlame, IconTrendingUp, IconCalendar, IconStar, IconRocket, IconShare } from '@tabler/icons-vue';

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
  
  const stats = `My Prediction Game Stats:
${props.user.total_winnings || 0} coins won
${props.user.current_streak || 0} day streak
${accuracyPercentage.value}% accuracy
${props.user.predictions_made || 0} predictions made

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