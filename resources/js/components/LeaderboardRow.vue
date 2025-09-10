<template>
  <div class="leaderboard-row" :class="rowClasses">
    <!-- Rank -->
    <div class="rank-cell">
      <div class="rank-container">
        <span class="rank-number">{{ displayRank }}</span>
        <div v-if="showTrend && entry.trend" class="trend-indicator" :class="entry.trend">
          <IconTrendingUp v-if="entry.trend === 'up'" class="h-4 w-4" />
          <IconTrendingDown v-else-if="entry.trend === 'down'" class="h-4 w-4" />
          <IconMinus v-else-if="entry.trend === 'same'" class="h-4 w-4" />
        </div>
      </div>
      <Badge v-if="isTopThree" :variant="rankBadgeVariant" class="text-xs">
        <IconTrophy class="h-3 w-3 mr-1" />
        {{ displayRank }}
      </Badge>
    </div>

    <!-- Player Info -->
    <div class="player-cell">
      <Avatar class="h-10 w-10">
        <AvatarImage v-if="entry.avatar_url" :src="entry.avatar_url" :alt="playerDisplayName" />
        <AvatarFallback>{{ avatarInitial }}</AvatarFallback>
      </Avatar>
      <div class="player-info">
        <div class="player-name">
          {{ playerDisplayName }}
          <Badge v-if="isCurrentUser" size="sm" class="ml-2 text-xs">You</Badge>
        </div>
        <div class="player-username" v-if="entry.username">
          @{{ entry.username }}
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-cell">
      <div class="stat-item">
        <span class="stat-label">Predictions</span>
        <span class="stat-value">{{ entry.predictions_made }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-label">Accuracy</span>
        <span class="stat-value accuracy" :class="accuracyClass">{{ entry.accuracy_percentage }}%</span>
      </div>
      <div class="stat-item">
        <span class="stat-label">Streak</span>
        <span class="stat-value streak">{{ entry.current_streak }}</span>
      </div>
    </div>

    <!-- Winnings -->
    <div class="winnings-cell">
      <div class="winnings-amount">
        <IconCoins class="h-4 w-4 text-yellow-500" />
        <span class="winnings-value">{{ formattedWinnings }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
// Import shadcn-vue components
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
// Import Tabler icons
import { IconTrophy, IconCoins, IconTrendingUp, IconTrendingDown, IconMinus } from '@tabler/icons-vue';

// LeaderboardEntry interface
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
  trend?: 'up' | 'down' | 'same';
  avatar_url?: string;
}

interface Props {
  entry: LeaderboardEntry;
  rank?: number;
  isCurrentUser?: boolean;
  showTrend?: boolean;
  showPosition?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  showTrend: false,
  showPosition: false
});

// Computed properties
const displayRank = computed(() => {
  return props.rank || props.entry.rank;
});

const isTopThree = computed(() => {
  return displayRank.value <= 3;
});

const rankBadgeVariant = computed(() => {
  switch (displayRank.value) {
    case 1: return 'default' as const; // Gold
    case 2: return 'secondary' as const; // Silver  
    case 3: return 'outline' as const; // Bronze
    default: return 'outline' as const;
  }
});


const playerDisplayName = computed(() => {
  const firstName = props.entry.first_name;
  const lastName = props.entry.last_name;
  return lastName ? `${firstName} ${lastName}` : firstName;
});

const avatarInitial = computed(() => {
  return props.entry.first_name.charAt(0).toUpperCase();
});

const accuracyClass = computed(() => {
  const accuracy = props.entry.accuracy_percentage;
  if (accuracy >= 70) return 'excellent';
  if (accuracy >= 60) return 'good';
  if (accuracy >= 50) return 'average';
  return 'poor';
});

const formattedWinnings = computed(() => {
  const winnings = props.entry.total_winnings;
  if (winnings >= 1000000) {
    return `${(winnings / 1000000).toFixed(1)}M`;
  } else if (winnings >= 1000) {
    return `${(winnings / 1000).toFixed(1)}K`;
  }
  return winnings.toString();
});

const rowClasses = computed(() => ({
  'current-user': props.isCurrentUser,
  'top-three': isTopThree.value,
  [`rank-${displayRank.value}`]: isTopThree.value
}));
</script>

<style scoped>
.leaderboard-row {
  display: grid;
  grid-template-columns: 4rem 1fr 6rem 5rem;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--tg-color-bg-secondary, white);
  border-radius: 0.75rem;
  border: 1px solid var(--tg-color-bg, #e5e7eb);
  transition: all 0.2s ease;
  align-items: center;
}

.leaderboard-row:hover {
  border-color: var(--tg-color-button, #2563eb);
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
}

.leaderboard-row.current-user {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-color: var(--tg-color-button, #2563eb);
}

.leaderboard-row.top-three {
  position: relative;
  overflow: hidden;
}

.leaderboard-row.rank-1 {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-color: #f59e0b;
}

.leaderboard-row.rank-2 {
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
  border-color: #9ca3af;
}

.leaderboard-row.rank-3 {
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
  border-color: #f87171;
}

/* Rank Cell */
.rank-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.rank-container {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.rank-number {
  font-size: 1.125rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
}

.trend-indicator {
  font-size: 0.875rem;
}

.trend-indicator.up {
  color: #10b981;
}

.trend-indicator.down {
  color: #ef4444;
}

.trend-indicator.same {
  color: var(--tg-color-hint, #6b7280);
}

.rank-badge {
  font-size: 1.25rem;
  line-height: 1;
}

/* Player Cell */
.player-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.player-avatar {
  flex-shrink: 0;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  overflow: hidden;
}

.avatar-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-fallback {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 1rem;
}

.player-info {
  min-width: 0;
  flex: 1;
}

.player-name {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
  font-size: 0.975rem;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.you-badge {
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  border-radius: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.025em;
  flex-shrink: 0;
}

.player-username {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Stats Cell */
.stats-cell {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  text-align: center;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.125rem;
}

.stat-label {
  font-size: 0.75rem;
  color: var(--tg-color-hint, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.stat-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
}

.stat-value.accuracy.excellent {
  color: #10b981;
}

.stat-value.accuracy.good {
  color: #059669;
}

.stat-value.accuracy.average {
  color: #f59e0b;
}

.stat-value.accuracy.poor {
  color: #ef4444;
}

.stat-value.streak {
  color: #f59e0b;
}

/* Winnings Cell */
.winnings-cell {
  text-align: center;
}

.winnings-amount {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.winnings-icon {
  font-size: 1.125rem;
}

.winnings-value {
  font-size: 0.975rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
}
</style>