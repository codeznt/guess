<template>
  <Card>
    <CardContent class="p-0">
      <!-- Table Header -->
      <div class="table-header">
        <div class="header-cell rank">Rank</div>
        <div class="header-cell player">Player</div>
        <div class="header-cell stats">Stats</div>
        <div class="header-cell winnings">Winnings</div>
      </div>

      <!-- Current User Position (if not in top results) -->
      <div v-if="userPosition && !isUserInTopResults" class="user-position-card">
        <Separator class="my-4" />
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
      <div v-if="entries.length === 0" class="empty-state p-12 text-center">
        <IconChartBar class="h-16 w-16 mx-auto mb-4 text-muted-foreground" />
        <h3 class="text-lg font-semibold mb-2">No Rankings Yet</h3>
        <p class="text-muted-foreground">
          {{ emptyMessage || 'Be the first to make predictions and claim the top spot!' }}
        </p>
      </div>

      <!-- Load More Button -->
      <div v-if="hasMore && !isLoading" class="p-4 border-t">
        <Button variant="outline" @click="loadMore" class="w-full">
          <IconTrendingUp class="h-4 w-4 mr-2" />
          Load More Rankings
        </Button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-8 text-center">
        <div class="flex items-center justify-center gap-2">
          <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
          <span class="text-muted-foreground">Loading rankings...</span>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import LeaderboardRow from './LeaderboardRow.vue';
// Import shadcn-vue components
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
// Import Tabler icons
import { IconChartBar, IconTrendingUp } from '@tabler/icons-vue';

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
  maxVisible: 50,
  entries: () => []
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


<style scoped>
/* Table Header */
.table-header {
  display: grid;
  grid-template-columns: 4rem 1fr 6rem 5rem;
  gap: 0.75rem;
  padding: 1rem;
  background: hsl(var(--muted));
  border-bottom: 1px solid hsl(var(--border));
}

.header-cell {
  font-size: 0.875rem;
  font-weight: 600;
  color: hsl(var(--muted-foreground));
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

.divider-text {
  background: hsl(var(--background));
  color: hsl(var(--muted-foreground));
  font-size: 0.875rem;
  font-weight: 500;
  padding: 0 1rem;
  position: relative;
  z-index: 2;
}

.user-row {
  border: 2px solid hsl(var(--primary));
  border-radius: 0.75rem;
  background: hsl(var(--background));
}

/* Table Body */
.table-body {
  display: flex;
  flex-direction: column;
}

/* Mobile Responsive */
@media (max-width: 640px) {
  .table-header {
    grid-template-columns: 3rem 1fr 4.5rem 4rem;
    gap: 0.5rem;
    padding: 0.875rem;
  }
  
  .header-cell {
    font-size: 0.8125rem;
  }
}

@media (max-width: 480px) {
  .table-header {
    grid-template-columns: 2.5rem 1fr 3.5rem 3.5rem;
    gap: 0.375rem;
    padding: 0.75rem;
  }
}
</style>