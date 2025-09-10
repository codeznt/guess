<template>
  <div class="questions-page">
    <!-- Header -->
    <Card class="mb-6">
      <CardContent class="pt-6">
        <div class="header-content">
          <div class="header-info">
            <h1 class="page-title flex items-center gap-2">
              <IconTarget class="h-6 w-6" />
              Daily Questions
            </h1>
            <p class="page-subtitle">Make your predictions and win coins!</p>
          </div>
          <div class="user-coins">
            <Badge variant="secondary" class="flex items-center gap-2 px-3 py-2 text-lg">
              <IconCoins class="h-5 w-5" />
              {{ formatNumber(user.daily_coins) }}
            </Badge>
          </div>
        </div>
        
        <!-- Progress Section -->
        <div class="progress-section">
          <div class="progress-header">
            <span class="progress-text">Progress: {{ answeredCount }} / {{ (questions && Array.isArray(questions)) ? questions.length : 0 }}</span>
            <span class="time-remaining flex items-center gap-1">
              <IconClock class="h-4 w-4" />
              {{ timeUntilReset || 'Loading...' }}
            </span>
          </div>
          <Progress :model-value="progressPercentage" class="mt-2" />
        </div>
      </CardContent>
    </Card>

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
      <Card v-else class="text-center py-12">
        <CardContent>
          <IconCalendar class="h-16 w-16 mx-auto mb-4 text-muted-foreground" />
          <CardTitle class="mb-2">No Questions Available</CardTitle>
          <CardDescription class="mb-6">
            New daily questions will be available soon. Check back later!
          </CardDescription>
          <Button as-child>
            <Link :href="dashboard.url()">
              <IconHome class="h-4 w-4 mr-2" />
              Back to Dashboard
            </Link>
          </Button>
        </CardContent>
      </Card>

      <!-- Completed State -->
      <Dialog :open="allQuestionsAnswered && questions && questions.length > 0">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <div class="flex justify-center mb-4">
              <IconTrophy class="h-16 w-16 text-yellow-500" />
            </div>
            <DialogTitle class="text-center">All Questions Completed!</DialogTitle>
            <DialogDescription class="text-center">
              Great job! You've answered all {{ (questions && Array.isArray(questions)) ? questions.length : 0 }} questions today.
              <br>Come back tomorrow for new challenges!
            </DialogDescription>
          </DialogHeader>
          
          <!-- Summary Stats -->
          <div class="grid grid-cols-3 gap-4 py-4">
            <div class="text-center">
              <div class="text-2xl font-bold">{{ correctPredictions }}</div>
              <div class="text-sm text-muted-foreground">Correct</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold">{{ totalWinnings }}</div>
              <div class="text-sm text-muted-foreground">Coins Won</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold">{{ user?.current_streak || 0 }}</div>
              <div class="text-sm text-muted-foreground">Current Streak</div>
            </div>
          </div>

          <DialogFooter class="flex flex-col gap-2 sm:flex-row">
            <Button variant="outline" as-child>
              <Link :href="leaderboard.index.url()">
                <IconTrophy class="h-4 w-4 mr-2" />
                View Leaderboard
              </Link>
            </Button>
            <Button as-child>
              <Link :href="dashboard.url()">
                <IconHome class="h-4 w-4 mr-2" />
                Dashboard
              </Link>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>

    <!-- Quick Tips -->
    <Card v-if="!allQuestionsAnswered" class="mt-6">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <IconBulb class="h-5 w-5" />
          Quick Tips
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid gap-4 md:grid-cols-3">
          <div class="flex items-start gap-3 p-4 border rounded-lg">
            <IconFlame class="h-5 w-5 text-orange-500 mt-0.5" />
            <div>
              <div class="font-medium">Build Streaks</div>
              <div class="text-sm text-muted-foreground">Correct predictions in a row increase your multiplier!</div>
            </div>
          </div>
          <div class="flex items-start gap-3 p-4 border rounded-lg">
            <IconCoins class="h-5 w-5 text-yellow-500 mt-0.5" />
            <div>
              <div class="font-medium">Smart Betting</div>
              <div class="text-sm text-muted-foreground">Bet more when you're confident, less when uncertain.</div>
            </div>
          </div>
          <div class="flex items-start gap-3 p-4 border rounded-lg">
            <IconRefresh class="h-5 w-5 text-blue-500 mt-0.5" />
            <div>
              <div class="font-medium">Daily Reset</div>
              <div class="text-sm text-muted-foreground">New questions and fresh coins every day!</div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
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
// Import shadcn-vue components
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
// Import Tabler icons
import { IconTarget, IconCoins, IconClock, IconCalendar, IconHome, IconTrophy, IconBulb, IconFlame, IconRefresh } from '@tabler/icons-vue';

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
  padding: 1rem;
  padding-bottom: 2rem;
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
  margin: 0 0 0.5rem 0;
}

.page-subtitle {
  color: hsl(var(--muted-foreground));
  margin: 0;
  font-size: 1rem;
}

.progress-section {
  margin-top: 1.5rem;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
}

.streak-display {
  margin-bottom: 1.5rem;
}

.questions-container {
  position: relative;
  margin-bottom: 2rem;
}

.questions-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
}

/* Responsive Design */
@media (max-width: 768px) {
  .questions-page {
    padding: 0.75rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .page-title {
    font-size: 1.5rem;
  }
  
  .questions-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .progress-header {
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
  }
}

@media (max-width: 480px) {
  .questions-page {
    padding: 0.5rem;
  }
}
</style>