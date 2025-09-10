<template>
  <div class="leaderboard-page">
    <!-- Header -->
    <Card class="mb-6">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <IconTrophy class="h-6 w-6" />
          Leaderboard
        </CardTitle>
        <CardDescription>See how you rank against other players</CardDescription>
      </CardHeader>
      <CardContent>
        <!-- Period Tabs -->
        <div class="flex p-1 bg-muted rounded-lg">
          <button 
            v-for="period in periods" 
            :key="period.value"
            @click="activePeriod = period.value"
            :class="[
              'flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all',
              activePeriod === period.value 
                ? 'bg-background text-foreground shadow-sm' 
                : 'text-muted-foreground hover:text-foreground'
            ]"
          >
            {{ period.label }}
          </button>
        </div>
      </CardContent>
    </Card>

    <!-- User Position Card -->
    <Card v-if="userPosition" class="mb-6 bg-gradient-to-r from-primary/10 to-primary/5 border-primary/20">
      <CardContent class="pt-6">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <IconUser class="h-5 w-5" />
            <span class="font-medium">Your Position</span>
          </div>
          <Badge variant="outline">{{ currentPeriodLabel }}</Badge>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-center">
            <div class="text-3xl font-bold text-primary">#{{ userPosition.rank }}</div>
            <div class="text-sm text-muted-foreground">Rank</div>
          </div>
          <div class="flex items-center gap-3 flex-1">
            <Avatar class="h-12 w-12 border-2 border-primary/20">
              <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
              <AvatarFallback>{{ user.name.charAt(0).toUpperCase() }}</AvatarFallback>
            </Avatar>
            <div>
              <div class="font-semibold">{{ user.name }}</div>
              <div class="text-sm text-muted-foreground">
                {{ userPosition.score }} points • {{ userPosition.accuracy }}% accuracy
              </div>
            </div>
          </div>
          <div v-if="userPosition.change" class="text-center">
            <div class="flex items-center gap-1" :class="{
              'text-green-600': userPosition.change > 0,
              'text-red-600': userPosition.change < 0,
              'text-muted-foreground': userPosition.change === 0
            }">
              <IconTrendingUp v-if="userPosition.change > 0" class="h-4 w-4" />
              <IconTrendingDown v-else-if="userPosition.change < 0" class="h-4 w-4" />
              <IconMinus v-else class="h-4 w-4" />
              <span class="font-medium">{{ Math.abs(userPosition.change) }}</span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

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
    <Card class="mt-6">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <IconChartBar class="h-5 w-5" />
          Competition Stats
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex items-center gap-3 p-4 border rounded-lg">
            <IconUsers class="h-8 w-8 text-blue-500" />
            <div>
              <div class="text-2xl font-bold">{{ formatNumber(stats.total_players) }}</div>
              <div class="text-sm text-muted-foreground">Total Players</div>
            </div>
          </div>
          <div class="flex items-center gap-3 p-4 border rounded-lg">
            <IconTarget class="h-8 w-8 text-green-500" />
            <div>
              <div class="text-2xl font-bold">{{ formatNumber(stats.total_predictions) }}</div>
              <div class="text-sm text-muted-foreground">Predictions Made</div>
            </div>
          </div>
          <div class="flex items-center gap-3 p-4 border rounded-lg">
            <IconCoins class="h-8 w-8 text-yellow-500" />
            <div>
              <div class="text-2xl font-bold">{{ formatNumber(stats.total_winnings) }}</div>
              <div class="text-sm text-muted-foreground">Coins Won</div>
            </div>
          </div>
          <div class="flex items-center gap-3 p-4 border rounded-lg">
            <IconTrendingUp class="h-8 w-8 text-purple-500" />
            <div>
              <div class="text-2xl font-bold">{{ stats.average_accuracy }}%</div>
              <div class="text-sm text-muted-foreground">Avg Accuracy</div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import LeaderboardTable from '@/components/LeaderboardTable.vue';
import { initializeTelegramMock } from '@/lib/telegram-mock';
// Import Wayfinder routes
import { dashboard } from '@/routes';
// Import shadcn-vue components
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
// Import Tabler icons
import { IconTrophy, IconUser, IconTrendingUp, IconTrendingDown, IconMinus, IconChartBar, IconUsers, IconTarget, IconCoins } from '@tabler/icons-vue';

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
  padding: 1rem;
  padding-bottom: 2rem;
}

.leaderboard-container {
  margin-bottom: 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .leaderboard-page {
    padding: 0.75rem;
  }
}

@media (max-width: 480px) {
  .leaderboard-page {
    padding: 0.5rem;
  }
}
</style>