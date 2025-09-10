<template>
  <div class="streak-display" :class="streakClass">
    <!-- Streak Header -->
    <div class="streak-header">
      <div class="streak-icon-container">
        <span class="streak-icon">{{ streakIcon }}</span>
        <div v-if="currentStreak > 0" class="streak-glow"></div>
      </div>
      <div class="streak-info">
        <h3 class="streak-title">{{ streakTitle }}</h3>
        <p class="streak-subtitle">{{ streakSubtitle }}</p>
      </div>
    </div>

    <!-- Current Streak -->
    <div class="current-streak">
      <div class="streak-number-container">
        <span class="streak-number">{{ currentStreak }}</span>
        <span class="streak-label">{{ currentStreak === 1 ? 'Day' : 'Days' }}</span>
      </div>
      
      <!-- Streak Progress Bar -->
      <div class="streak-progress">
        <div class="progress-bar">
          <div 
            class="progress-fill" 
            :style="{ width: `${progressPercentage}%` }"
          ></div>
        </div>
        <div class="progress-info">
          <span class="progress-current">{{ currentStreak }}</span>
          <span class="progress-target">{{ nextMilestone }}</span>
        </div>
      </div>
    </div>

    <!-- Streak Stats -->
    <div class="streak-stats">
      <div class="stat-item">
        <span class="stat-icon">⭐</span>
        <div class="stat-content">
          <span class="stat-value">{{ bestStreak }}</span>
          <span class="stat-label">Best Streak</span>
        </div>
      </div>
      <div class="stat-item">
        <span class="stat-icon">🎯</span>
        <div class="stat-content">
          <span class="stat-value">{{ totalCorrect }}</span>
          <span class="stat-label">Total Correct</span>
        </div>
      </div>
      <div class="stat-item">
        <span class="stat-icon">🏆</span>
        <div class="stat-content">
          <span class="stat-value">{{ streakBonus }}x</span>
          <span class="stat-label">Streak Bonus</span>
        </div>
      </div>
    </div>

    <!-- Streak Milestones -->
    <div v-if="showMilestones" class="streak-milestones">
      <h4 class="milestones-title">Streak Milestones</h4>
      <div class="milestones-grid">
        <div 
          v-for="milestone in milestones" 
          :key="milestone.days"
          class="milestone-item"
          :class="{ 'achieved': milestone.achieved, 'next': milestone.isNext }"
        >
          <div class="milestone-icon">{{ milestone.icon }}</div>
          <div class="milestone-content">
            <span class="milestone-days">{{ milestone.days }} days</span>
            <span class="milestone-title">{{ milestone.title }}</span>
            <span class="milestone-bonus">{{ milestone.bonus }}x bonus</span>
          </div>
          <div v-if="milestone.achieved" class="milestone-checkmark">✓</div>
        </div>
      </div>
    </div>

    <!-- Streak Actions -->
    <div class="streak-actions">
      <button 
        v-if="canContinueToday && !todayPredicted"
        @click="goToQuestions" 
        class="action-button primary"
      >
        <span class="button-icon">🔥</span>
        <span class="button-text">Continue Streak</span>
      </button>
      
      <button 
        v-if="currentStreak === 0"
        @click="goToQuestions" 
        class="action-button secondary"
      >
        <span class="button-icon">🚀</span>
        <span class="button-text">Start New Streak</span>
      </button>
      
      <button 
        v-if="currentStreak > 0 && showShare"
        @click="shareStreak" 
        class="action-button share"
      >
        <span class="button-icon">📤</span>
        <span class="button-text">Share Streak</span>
      </button>
    </div>

    <!-- Streak Tips -->
    <div v-if="showTips" class="streak-tips">
      <div class="tip-header">
        <span class="tip-icon">💡</span>
        <span class="tip-title">Streak Tips</span>
      </div>
      <div class="tip-content">
        <ul class="tips-list">
          <li v-for="tip in streakTips" :key="tip" class="tip-item">{{ tip }}</li>
        </ul>
      </div>
    </div>

    <!-- Streak Warning -->
    <div v-if="showWarning" class="streak-warning">
      <div class="warning-content">
        <span class="warning-icon">⚠️</span>
        <div class="warning-text">
          <span class="warning-title">{{ warningTitle }}</span>
          <span class="warning-message">{{ warningMessage }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

// Props
interface Props {
  currentStreak: number;
  bestStreak: number;
  totalCorrect?: number;
  todayPredicted?: boolean;
  canContinueToday?: boolean;
  showMilestones?: boolean;
  showTips?: boolean;
  showShare?: boolean;
  lastPredictionDate?: string;
}

const props = withDefaults(defineProps<Props>(), {
  totalCorrect: 0,
  todayPredicted: false,
  canContinueToday: true,
  showMilestones: true,
  showTips: false,
  showShare: true
});

// Emits
const emit = defineEmits<{
  shareStreak: [streak: number];
  continueStreak: [];
}>();

// Computed properties
const streakClass = computed(() => {
  const streak = props.currentStreak;
  if (streak === 0) return 'no-streak';
  if (streak >= 30) return 'legendary';
  if (streak >= 14) return 'epic';
  if (streak >= 7) return 'hot';
  if (streak >= 3) return 'warm';
  return 'building';
});

const streakIcon = computed(() => {
  const streak = props.currentStreak;
  if (streak === 0) return '💤';
  if (streak >= 30) return '👑';
  if (streak >= 14) return '🚀';
  if (streak >= 7) return '🔥';
  if (streak >= 3) return '⚡';
  return '🌱';
});

const streakTitle = computed(() => {
  const streak = props.currentStreak;
  if (streak === 0) return 'No Active Streak';
  if (streak >= 30) return 'Legendary Streak!';
  if (streak >= 14) return 'Epic Streak!';
  if (streak >= 7) return 'Hot Streak!';
  if (streak >= 3) return 'Building Momentum';
  return 'Getting Started';
});

const streakSubtitle = computed(() => {
  const streak = props.currentStreak;
  if (streak === 0) return 'Make your first prediction to start a streak';
  if (streak >= 30) return 'You are a prediction master!';
  if (streak >= 14) return 'Incredible consistency!';
  if (streak >= 7) return 'You\'re on fire!';
  if (streak >= 3) return 'Keep it going!';
  return 'Every streak starts with one prediction';
});

const nextMilestone = computed(() => {
  const streak = props.currentStreak;
  const milestones = [3, 7, 14, 30, 50, 100];
  return milestones.find(m => m > streak) || streak + 10;
});

const progressPercentage = computed(() => {
  const streak = props.currentStreak;
  const target = nextMilestone.value;
  
  if (streak === 0) return 0;
  if (streak >= 100) return 100;
  
  // Find the previous milestone
  const milestones = [0, 3, 7, 14, 30, 50, 100];
  const currentMilestone = milestones.findLast(m => m <= streak) || 0;
  
  const progress = ((streak - currentMilestone) / (target - currentMilestone)) * 100;
  return Math.min(Math.max(progress, 5), 100); // Min 5% for visibility
});

const streakBonus = computed(() => {
  const streak = props.currentStreak;
  if (streak >= 30) return 3.0;
  if (streak >= 14) return 2.5;
  if (streak >= 7) return 2.0;
  if (streak >= 3) return 1.5;
  return 1.0;
});

const milestones = computed(() => [
  {
    days: 3,
    title: 'First Steps',
    icon: '🌱',
    bonus: 1.5,
    achieved: props.currentStreak >= 3,
    isNext: props.currentStreak < 3
  },
  {
    days: 7,
    title: 'Weekly Warrior',
    icon: '⚡',
    bonus: 2.0,
    achieved: props.currentStreak >= 7,
    isNext: props.currentStreak >= 3 && props.currentStreak < 7
  },
  {
    days: 14,
    title: 'Fortnight Fighter',
    icon: '🔥',
    bonus: 2.5,
    achieved: props.currentStreak >= 14,
    isNext: props.currentStreak >= 7 && props.currentStreak < 14
  },
  {
    days: 30,
    title: 'Monthly Master',
    icon: '👑',
    bonus: 3.0,
    achieved: props.currentStreak >= 30,
    isNext: props.currentStreak >= 14 && props.currentStreak < 30
  }
]);

const streakTips = computed(() => {
  const tips = [
    'Make predictions every day to maintain your streak',
    'Higher accuracy increases your bonus multiplier',
    'Longer streaks unlock better achievement rewards',
    'Share your achievements to challenge friends'
  ];
  
  if (props.currentStreak >= 7) {
    tips.push('You\'re in the top 10% of active players!');
  }
  
  return tips.slice(0, 3); // Show max 3 tips
});

const showWarning = computed(() => {
  if (props.currentStreak === 0 || props.todayPredicted) return false;
  
  const lastPrediction = props.lastPredictionDate ? new Date(props.lastPredictionDate) : null;
  if (!lastPrediction) return false;
  
  const now = new Date();
  const yesterday = new Date(now);
  yesterday.setDate(yesterday.getDate() - 1);
  
  return lastPrediction.toDateString() !== yesterday.toDateString();
});

const warningTitle = computed(() => {
  const hoursLeft = getHoursUntilMidnight();
  if (hoursLeft <= 2) return 'Streak at Risk!';
  if (hoursLeft <= 6) return 'Don\'t Forget!';
  return 'Reminder';
});

const warningMessage = computed(() => {
  const hoursLeft = getHoursUntilMidnight();
  if (hoursLeft <= 2) return `Only ${hoursLeft} hours left to continue your ${props.currentStreak}-day streak`;
  if (hoursLeft <= 6) return `${hoursLeft} hours left to make today's predictions`;
  return 'Make your daily predictions to keep your streak alive';
});

// Methods
const getHoursUntilMidnight = (): number => {
  const now = new Date();
  const midnight = new Date(now);
  midnight.setHours(24, 0, 0, 0);
  return Math.ceil((midnight.getTime() - now.getTime()) / (1000 * 60 * 60));
};

const goToQuestions = () => {
  emit('continueStreak');
  router.visit('/questions/daily');
};

const shareStreak = () => {
  emit('shareStreak', props.currentStreak);
  
  const message = `🔥 I'm on a ${props.currentStreak}-day prediction streak! 
🎯 ${props.totalCorrect} correct predictions
⚡ ${streakBonus.value}x streak bonus

Join me in the daily prediction challenge!`;

  // Try native sharing first
  if (navigator.share) {
    navigator.share({
      title: `${props.currentStreak}-Day Prediction Streak!`,
      text: message,
      url: window.location.origin
    });
  } else {
    // Fallback to copying to clipboard
    navigator.clipboard?.writeText(message);
    // Could show a toast notification here
  }
};

// Lifecycle
onMounted(() => {
  // Add any animation or initialization logic here
});
</script>

<style scoped>
.streak-display {
  background: var(--tg-color-bg-secondary, white);
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  position: relative;
}

/* Streak Level Styling */
.streak-display.no-streak {
  border-left: 4px solid #9ca3af;
}

.streak-display.building {
  border-left: 4px solid #10b981;
}

.streak-display.warm {
  border-left: 4px solid #f59e0b;
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}

.streak-display.hot {
  border-left: 4px solid #ef4444;
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
}

.streak-display.epic {
  border-left: 4px solid #8b5cf6;
  background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
}

.streak-display.legendary {
  border-left: 4px solid #f59e0b;
  background: linear-gradient(135deg, #fffbeb 0%, #fde68a 100%);
  position: relative;
}

.streak-display.legendary::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, #f59e0b, #ef4444, #8b5cf6, #10b981, #f59e0b);
  background-size: 400% 100%;
  animation: rainbow 3s linear infinite;
}

@keyframes rainbow {
  0% { background-position: 0% 50%; }
  100% { background-position: 400% 50%; }
}

/* Streak Header */
.streak-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-bottom: 1px solid var(--tg-color-bg, #f1f5f9);
}

.streak-icon-container {
  position: relative;
  flex-shrink: 0;
}

.streak-icon {
  font-size: 2.5rem;
  display: block;
}

.streak-glow {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(239, 68, 68, 0.3) 0%, transparent 70%);
  animation: pulse-glow 2s ease-in-out infinite;
  z-index: -1;
}

@keyframes pulse-glow {
  0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.3; }
  50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.6; }
}

.streak-info {
  flex: 1;
  min-width: 0;
}

.streak-title {
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 0.25rem;
}

.streak-subtitle {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
  line-height: 1.4;
}

/* Current Streak */
.current-streak {
  padding: 1.5rem;
  text-align: center;
  border-bottom: 1px solid var(--tg-color-bg, #f1f5f9);
}

.streak-number-container {
  margin-bottom: 1rem;
}

.streak-number {
  font-size: 3rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
  display: block;
  line-height: 1;
}

.streak-label {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.25rem;
  display: block;
}

/* Streak Progress */
.streak-progress {
  max-width: 16rem;
  margin: 0 auto;
}

.progress-bar {
  height: 0.5rem;
  background: var(--tg-color-bg, #e5e7eb);
  border-radius: 0.25rem;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #10b981 0%, #059669 100%);
  border-radius: 0.25rem;
  transition: width 0.6s ease;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--tg-color-hint, #6b7280);
}

/* Streak Stats */
.streak-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  padding: 1.5rem;
  border-bottom: 1px solid var(--tg-color-bg, #f1f5f9);
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-align: center;
  flex-direction: column;
}

.stat-icon {
  font-size: 1.5rem;
  margin-bottom: 0.25rem;
}

.stat-content {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-value {
  font-size: 1.125rem;
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 0.125rem;
}

.stat-label {
  font-size: 0.75rem;
  color: var(--tg-color-hint, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

/* Streak Milestones */
.streak-milestones {
  padding: 1.5rem;
  border-bottom: 1px solid var(--tg-color-bg, #f1f5f9);
}

.milestones-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
  margin-bottom: 1rem;
}

.milestones-grid {
  display: grid;
  gap: 0.75rem;
}

.milestone-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  border-radius: 0.75rem;
  border: 2px solid var(--tg-color-bg, #e5e7eb);
  background: var(--tg-color-bg, #f8fafc);
  transition: all 0.2s ease;
  position: relative;
}

.milestone-item.achieved {
  border-color: #10b981;
  background: #d1fae5;
}

.milestone-item.next {
  border-color: var(--tg-color-button, #2563eb);
  background: #eff6ff;
}

.milestone-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}

.milestone-content {
  flex: 1;
  min-width: 0;
}

.milestone-days {
  font-weight: bold;
  color: var(--tg-color-text, #1f2937);
  font-size: 0.875rem;
  display: block;
}

.milestone-title {
  color: var(--tg-color-text, #1f2937);
  font-size: 0.875rem;
  display: block;
  margin: 0.125rem 0;
}

.milestone-bonus {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.75rem;
  display: block;
}

.milestone-checkmark {
  color: #10b981;
  font-weight: bold;
  font-size: 1.125rem;
}

/* Streak Actions */
.streak-actions {
  display: flex;
  gap: 0.75rem;
  padding: 1.5rem;
}

.action-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  flex: 1;
  padding: 1rem;
  border: none;
  border-radius: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
}

.action-button.primary {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.action-button.primary:hover {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
  transform: translateY(-1px);
}

.action-button.secondary {
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
}

.action-button.secondary:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.action-button.share {
  background: var(--tg-color-bg-secondary, white);
  color: var(--tg-color-text, #1f2937);
  border: 2px solid var(--tg-color-bg, #e5e7eb);
}

.action-button.share:hover {
  border-color: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button, #2563eb);
}

.button-icon {
  font-size: 1.125rem;
}

/* Streak Tips */
.streak-tips {
  padding: 1.5rem;
  background: var(--tg-color-bg, #f8fafc);
}

.tip-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.tip-icon {
  font-size: 1.25rem;
}

.tip-title {
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
}

.tips-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.tip-item {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
  line-height: 1.5;
  margin-bottom: 0.5rem;
  padding-left: 1rem;
  position: relative;
}

.tip-item::before {
  content: '•';
  color: var(--tg-color-button, #2563eb);
  position: absolute;
  left: 0;
}

.tip-item:last-child {
  margin-bottom: 0;
}

/* Streak Warning */
.streak-warning {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-top: 1px solid #f59e0b;
  padding: 1rem 1.5rem;
}

.warning-content {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.warning-icon {
  font-size: 1.25rem;
  flex-shrink: 0;
}

.warning-text {
  flex: 1;
  min-width: 0;
}

.warning-title {
  font-weight: 600;
  color: #92400e;
  display: block;
  margin-bottom: 0.125rem;
}

.warning-message {
  color: #a16207;
  font-size: 0.875rem;
  display: block;
}

/* Mobile Responsive */
@media (max-width: 640px) {
  .streak-header {
    padding: 1rem;
  }
  
  .streak-icon {
    font-size: 2rem;
  }
  
  .streak-title {
    font-size: 1.125rem;
  }
  
  .current-streak {
    padding: 1rem;
  }
  
  .streak-number {
    font-size: 2.5rem;
  }
  
  .streak-stats {
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    padding: 1rem;
  }
  
  .stat-icon {
    font-size: 1.25rem;
  }
  
  .stat-value {
    font-size: 1rem;
  }
  
  .milestones-grid {
    gap: 0.5rem;
  }
  
  .milestone-item {
    padding: 0.625rem;
    gap: 0.75rem;
  }
  
  .milestone-icon {
    font-size: 1.25rem;
  }
  
  .streak-actions {
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
  }
  
  .action-button {
    padding: 0.875rem;
  }
  
  .streak-tips,
  .streak-warning {
    padding: 1rem;
  }
}
</style>