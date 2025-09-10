<template>
  <div class="leaderboard-table">
    <!-- Table Header -->
    <div class="table-header">
      <div class="header-cell rank">Rank</div>
      <div class="header-cell player">Player</div>
      <div class="header-cell stats">Stats</div>
      <div class="header-cell winnings">Winnings</div>
    </div>

    <!-- Current User Position (if not in top results) -->
    <div 
      v-if="userPosition && !isUserInTopResults" 
      class="user-position-card"
    >
      <div class="position-divider">
        <span class="divider-text">Your Position</span>
      </div>
      <LeaderboardRow 
        :entry="userPosition" 
        :isCurrentUser="true"
        :showPosition="true"
        class="user-row"
      />
    </div>

    <!-- Leaderboard Entries -->
    <div class="table-body">
      <LeaderboardRow
        v-for="(entry, index) in entries"
        :key="entry.user_id"
        :entry="entry"
        :isCurrentUser="entry.user_id === currentUserId"
        :rank="entry.rank || (index + 1)"
        :showTrend="showTrend"
        :class="{ 'highlighted': entry.user_id === currentUserId }"
      />
    </div>

    <!-- Empty State -->
    <div v-if="entries.length === 0" class="empty-state">
      <div class="empty-icon">📊</div>
      <h3 class="empty-title">No Rankings Yet</h3>
      <p class="empty-description">
        {{ emptyMessage || 'Be the first to make predictions and claim the top spot!' }}
      </p>
    </div>

    <!-- Load More Button -->
    <div v-if="hasMore && !isLoading" class="load-more-section">
      <button @click="loadMore" class="load-more-button">
        <span class="button-icon">📈</span>
        Load More Rankings
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-state">
      <div class="loading-spinner"></div>
      <span class="loading-text">Loading rankings...</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import LeaderboardRow from './LeaderboardRow.vue';

// Props
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
  entries: LeaderboardEntry[];
  currentUserId?: number;
  userPosition?: LeaderboardEntry;
  showTrend?: boolean;
  isLoading?: boolean;
  hasMore?: boolean;
  emptyMessage?: string;
  maxVisible?: number;
}

const props = withDefaults(defineProps<Props>(), {
  showTrend: false,
  isLoading: false,
  hasMore: false,
  maxVisible: 50
});

// Emits
const emit = defineEmits<{
  loadMore: [];
}>();

// Computed properties
const isUserInTopResults = computed(() => {
  if (!props.userPosition || !props.currentUserId) return false;
  return props.entries.some(entry => entry.user_id === props.currentUserId);
});

// Methods
const loadMore = () => {
  emit('loadMore');
};
</script>

<script setup lang="ts">
// LeaderboardRow component definition (inline for simplicity)
// In a real app, this would be a separate component file
</script>

<template>
  <!-- LeaderboardRow Component Template (inline) -->
  <div class="leaderboard-row" :class="rowClasses">
    <!-- Rank -->
    <div class="rank-cell">
      <div class="rank-container">
        <span class="rank-number">{{ displayRank }}</span>
        <div v-if="showTrend && entry.trend" class="trend-indicator" :class="entry.trend">
          <span class="trend-icon">{{ trendIcon }}</span>
        </div>
      </div>
      <div v-if="isTopThree" class="rank-badge" :class="rankBadgeClass">
        {{ rankBadgeText }}
      </div>
    </div>

    <!-- Player Info -->
    <div class="player-cell">
      <div class="player-avatar">
        <img 
          v-if="entry.avatar_url" 
          :src="entry.avatar_url" 
          :alt="playerDisplayName"
          class="avatar-image"
        />
        <div v-else class="avatar-fallback">
          {{ avatarInitial }}
        </div>
      </div>
      <div class="player-info">
        <div class="player-name">
          {{ playerDisplayName }}
          <span v-if="isCurrentUser" class="you-badge">You</span>
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
        <span class="winnings-icon">🪙</span>
        <span class="winnings-value">{{ formattedWinnings }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// LeaderboardRow Component Script
interface RowProps {
  entry: LeaderboardEntry;
  rank?: number;
  isCurrentUser?: boolean;
  showTrend?: boolean;
  showPosition?: boolean;
}

const rowProps = withDefaults(defineProps<RowProps>(), {
  showTrend: false,
  showPosition: false
});

// Computed properties for row
const displayRank = computed(() => {
  return rowProps.rank || rowProps.entry.rank;
});

const isTopThree = computed(() => {
  return displayRank.value <= 3;
});

const rankBadgeClass = computed(() => {
  switch (displayRank.value) {
    case 1: return 'gold';
    case 2: return 'silver';
    case 3: return 'bronze';
    default: return '';
  }
});

const rankBadgeText = computed(() => {
  switch (displayRank.value) {
    case 1: return '🥇';
    case 2: return '🥈';
    case 3: return '🥉';
    default: return '';
  }
});

const trendIcon = computed(() => {
  switch (rowProps.entry.trend) {
    case 'up': return '↗️';
    case 'down': return '↘️';
    case 'same': return '→';
    default: return '';
  }
});

const playerDisplayName = computed(() => {
  const firstName = rowProps.entry.first_name;
  const lastName = rowProps.entry.last_name;
  return lastName ? `${firstName} ${lastName}` : firstName;
});

const avatarInitial = computed(() => {
  return rowProps.entry.first_name.charAt(0).toUpperCase();
});

const accuracyClass = computed(() => {
  const accuracy = rowProps.entry.accuracy_percentage;
  if (accuracy >= 70) return 'excellent';
  if (accuracy >= 60) return 'good';
  if (accuracy >= 50) return 'average';
  return 'poor';
});

const formattedWinnings = computed(() => {
  const winnings = rowProps.entry.total_winnings;
  if (winnings >= 1000000) {
    return `${(winnings / 1000000).toFixed(1)}M`;
  } else if (winnings >= 1000) {
    return `${(winnings / 1000).toFixed(1)}K`;
  }
  return winnings.toString();
});

const rowClasses = computed(() => ({
  'current-user': rowProps.isCurrentUser,
  'top-three': isTopThree.value,
  [`rank-${displayRank.value}`]: isTopThree.value
}));
</script>

<style scoped>
.leaderboard-table {
  width: 100%;
}

/* Table Header */
.table-header {
  display: grid;
  grid-template-columns: 4rem 1fr 6rem 5rem;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--tg-color-bg, #f8fafc);
  border-radius: 0.75rem 0.75rem 0 0;
  border-bottom: 2px solid var(--tg-color-bg-secondary, #e5e7eb);
  margin-bottom: 0.5rem;
}

.header-cell {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--tg-color-hint, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.header-cell.rank {
  text-align: center;
}

.header-cell.stats,
.header-cell.winnings {
  text-align: center;
}

/* User Position Card */
.user-position-card {
  margin: 1rem 0;
}

.position-divider {
  text-align: center;
  margin-bottom: 0.75rem;
  position: relative;
}

.position-divider::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  height: 1px;
  background: var(--tg-color-bg, #e5e7eb);
  z-index: 1;
}

.divider-text {
  background: var(--tg-color-bg-secondary, white);
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
  font-weight: 500;
  padding: 0 1rem;
  position: relative;
  z-index: 2;
}

.user-row {
  border: 2px solid var(--tg-color-button, #2563eb);
  border-radius: 0.75rem;
  background: var(--tg-color-bg-secondary, white);
}

/* Table Body */
.table-body {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

/* Leaderboard Row */
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

.leaderboard-row.highlighted {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-color: #f59e0b;
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

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--tg-color-hint, #6b7280);
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.empty-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 0.5rem;
}

.empty-description {
  font-size: 0.975rem;
  line-height: 1.5;
}

/* Load More */
.load-more-section {
  text-align: center;
  margin-top: 1.5rem;
}

.load-more-button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1.5rem;
  background: var(--tg-color-bg-secondary, white);
  border: 2px solid var(--tg-color-button, #2563eb);
  border-radius: 0.75rem;
  color: var(--tg-color-button, #2563eb);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.load-more-button:hover {
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
}

.button-icon {
  font-size: 1rem;
}

/* Loading State */
.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem;
  color: var(--tg-color-hint, #6b7280);
}

.loading-spinner {
  width: 1.5rem;
  height: 1.5rem;
  border: 2px solid var(--tg-color-bg, #e5e7eb);
  border-top: 2px solid var(--tg-color-button, #2563eb);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-text {
  font-size: 0.975rem;
  font-weight: 500;
}

/* Mobile Responsive */
@media (max-width: 640px) {
  .table-header,
  .leaderboard-row {
    grid-template-columns: 3rem 1fr 4.5rem 4rem;
    gap: 0.5rem;
    padding: 0.875rem;
  }
  
  .header-cell {
    font-size: 0.8125rem;
  }
  
  .rank-number {
    font-size: 1rem;
  }
  
  .player-avatar {
    width: 2rem;
    height: 2rem;
  }
  
  .avatar-fallback {
    font-size: 0.875rem;
  }
  
  .player-name {
    font-size: 0.9rem;
  }
  
  .player-username {
    font-size: 0.8125rem;
  }
  
  .you-badge {
    font-size: 0.6875rem;
    padding: 0.1rem 0.375rem;
  }
  
  .stat-label {
    font-size: 0.6875rem;
  }
  
  .stat-value {
    font-size: 0.8125rem;
  }
  
  .winnings-value {
    font-size: 0.9rem;
  }
  
  .winnings-icon {
    font-size: 1rem;
  }
  
  .stats-cell {
    gap: 0.125rem;
  }
  
  .stat-item {
    gap: 0.0625rem;
  }
}

@media (max-width: 480px) {
  .table-header,
  .leaderboard-row {
    grid-template-columns: 2.5rem 1fr 3.5rem 3.5rem;
    gap: 0.375rem;
    padding: 0.75rem;
  }
  
  .player-name {
    font-size: 0.875rem;
  }
  
  .stat-value {
    font-size: 0.75rem;
  }
  
  .winnings-value {
    font-size: 0.8125rem;
  }
}
</style>