<template>
  <div class="betting-slider">
    <!-- Amount Input -->
    <div class="amount-input-container">
      <div class="amount-input-wrapper">
        <span class="coin-icon">🪙</span>
        <input
          ref="amountInput"
          v-model.number="inputValue"
          @input="handleInput"
          @blur="handleBlur"
          type="number"
          :min="min"
          :max="effectiveMax"
          :step="stepSize"
          class="amount-input"
          :class="{ 'error': hasError }"
        />
        <span class="coin-label">coins</span>
      </div>
      <div v-if="hasError" class="error-message">
        {{ errorMessage }}
      </div>
    </div>

    <!-- Slider -->
    <div class="slider-container">
      <input
        ref="sliderRef"
        v-model.number="sliderValue"
        @input="handleSliderInput"
        type="range"
        :min="min"
        :max="effectiveMax"
        :step="stepSize"
        class="slider"
        :style="sliderStyle"
      />
      
      <!-- Slider Track Labels -->
      <div class="slider-labels">
        <span class="slider-label min">{{ min }}</span>
        <span class="slider-label max">{{ effectiveMax }}</span>
      </div>
    </div>

    <!-- Quick Bet Buttons -->
    <div class="quick-bets">
      <button
        v-for="preset in quickBetPresets"
        :key="preset.label"
        @click="setQuickBet(preset.value)"
        :disabled="preset.value > effectiveMax"
        class="quick-bet-button"
        :class="{ 'active': isActivePreset(preset.value) }"
      >
        {{ preset.label }}
      </button>
      <button
        @click="setMaxBet"
        :disabled="effectiveMax <= min"
        class="quick-bet-button max-bet"
        :class="{ 'active': isAtMax }"
      >
        MAX
      </button>
    </div>

    <!-- Bet Summary -->
    <div class="bet-summary">
      <div class="summary-row">
        <span class="summary-label">Bet Amount:</span>
        <span class="summary-value">{{ currentValue }} coins</span>
      </div>
      <div class="summary-row">
        <span class="summary-label">Remaining:</span>
        <span class="summary-value">{{ remainingCoins }} coins</span>
      </div>
      <div v-if="percentage > 0" class="summary-row percentage">
        <span class="summary-label">% of Balance:</span>
        <span class="summary-value">{{ percentage }}%</span>
      </div>
    </div>

    <!-- Risk Indicator -->
    <div class="risk-indicator" :class="riskLevel">
      <div class="risk-bar">
        <div 
          class="risk-fill" 
          :style="{ width: `${Math.min(percentage, 100)}%` }"
        ></div>
      </div>
      <div class="risk-labels">
        <span class="risk-label">{{ riskLabel }}</span>
        <span class="risk-percentage">{{ percentage }}%</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';

// Props
interface Props {
  modelValue: number;
  min?: number;
  max?: number;
  userCoins: number;
  step?: number;
}

const props = withDefaults(defineProps<Props>(), {
  min: 10,
  max: 500,
  step: 5
});

// Emits
const emit = defineEmits<{
  'update:modelValue': [value: number];
}>();

// Refs
const amountInput = ref<HTMLInputElement>();
const sliderRef = ref<HTMLInputElement>();

// Reactive state
const inputValue = ref<number>(props.modelValue);
const sliderValue = ref<number>(props.modelValue);
const hasError = ref<boolean>(false);
const errorMessage = ref<string>('');

// Computed properties
const effectiveMax = computed(() => {
  return Math.min(props.max, props.userCoins);
});

const stepSize = computed(() => {
  return props.step;
});

const currentValue = computed(() => {
  return Math.max(props.min, Math.min(effectiveMax.value, inputValue.value || props.min));
});

const remainingCoins = computed(() => {
  return Math.max(0, props.userCoins - currentValue.value);
});

const percentage = computed(() => {
  if (props.userCoins === 0) return 0;
  return Math.round((currentValue.value / props.userCoins) * 100);
});

const riskLevel = computed(() => {
  const pct = percentage.value;
  if (pct <= 25) return 'low';
  if (pct <= 50) return 'medium';
  if (pct <= 75) return 'high';
  return 'extreme';
});

const riskLabel = computed(() => {
  switch (riskLevel.value) {
    case 'low': return 'Conservative';
    case 'medium': return 'Moderate';
    case 'high': return 'Aggressive';
    case 'extreme': return 'All-in';
    default: return 'Conservative';
  }
});

const quickBetPresets = computed(() => {
  const basePresets = [
    { label: '10', value: 10 },
    { label: '25', value: 25 },
    { label: '50', value: 50 },
    { label: '100', value: 100 }
  ];
  
  // Add percentage-based presets
  const percentagePresets = [
    { label: '25%', value: Math.round(props.userCoins * 0.25) },
    { label: '50%', value: Math.round(props.userCoins * 0.5) }
  ].filter(preset => preset.value >= props.min && preset.value <= effectiveMax.value);
  
  return [...basePresets, ...percentagePresets]
    .filter(preset => preset.value <= effectiveMax.value)
    .sort((a, b) => a.value - b.value);
});

const isAtMax = computed(() => {
  return currentValue.value >= effectiveMax.value;
});

const sliderStyle = computed(() => {
  const progress = ((currentValue.value - props.min) / (effectiveMax.value - props.min)) * 100;
  return {
    background: `linear-gradient(90deg, var(--tg-color-button, #2563eb) 0%, var(--tg-color-button, #2563eb) ${progress}%, var(--tg-color-bg, #e5e7eb) ${progress}%, var(--tg-color-bg, #e5e7eb) 100%)`
  };
});

// Methods
const validateValue = (value: number): { isValid: boolean; error?: string } => {
  if (isNaN(value) || value < props.min) {
    return { isValid: false, error: `Minimum bet is ${props.min} coins` };
  }
  
  if (value > effectiveMax.value) {
    return { isValid: false, error: `Maximum bet is ${effectiveMax.value} coins` };
  }
  
  if (value > props.userCoins) {
    return { isValid: false, error: 'Insufficient coins' };
  }
  
  return { isValid: true };
};

const updateValue = (newValue: number) => {
  const clampedValue = Math.max(props.min, Math.min(effectiveMax.value, newValue));
  
  inputValue.value = clampedValue;
  sliderValue.value = clampedValue;
  
  const validation = validateValue(clampedValue);
  hasError.value = !validation.isValid;
  errorMessage.value = validation.error || '';
  
  if (validation.isValid) {
    emit('update:modelValue', clampedValue);
  }
};

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const value = parseInt(target.value) || props.min;
  
  updateValue(value);
  sliderValue.value = currentValue.value;
};

const handleSliderInput = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const value = parseInt(target.value);
  
  updateValue(value);
  inputValue.value = currentValue.value;
};

const handleBlur = () => {
  // Ensure the input value is valid when focus is lost
  if (inputValue.value < props.min) {
    updateValue(props.min);
  } else if (inputValue.value > effectiveMax.value) {
    updateValue(effectiveMax.value);
  }
};

const setQuickBet = (value: number) => {
  updateValue(value);
};

const setMaxBet = () => {
  updateValue(effectiveMax.value);
};

const isActivePreset = (value: number): boolean => {
  return currentValue.value === value;
};

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  if (newValue !== currentValue.value) {
    updateValue(newValue);
  }
});

watch(() => props.userCoins, () => {
  // Re-validate when user coins change
  const validation = validateValue(currentValue.value);
  hasError.value = !validation.isValid;
  errorMessage.value = validation.error || '';
  
  // Adjust value if it exceeds new limits
  if (currentValue.value > effectiveMax.value) {
    updateValue(effectiveMax.value);
  }
});

// Initialize
updateValue(props.modelValue);
</script>

<style scoped>
.betting-slider {
  width: 100%;
}

/* Amount Input */
.amount-input-container {
  margin-bottom: 1.5rem;
}

.amount-input-wrapper {
  display: flex;
  align-items: center;
  background: var(--tg-color-bg-secondary, white);
  border: 2px solid var(--tg-color-bg, #e5e7eb);
  border-radius: 0.75rem;
  padding: 0.75rem 1rem;
  transition: border-color 0.2s ease;
}

.amount-input-wrapper:focus-within {
  border-color: var(--tg-color-button, #2563eb);
}

.amount-input-wrapper.error {
  border-color: #ef4444;
}

.coin-icon {
  font-size: 1.25rem;
  margin-right: 0.5rem;
  flex-shrink: 0;
}

.amount-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
  text-align: center;
  min-width: 0;
}

.amount-input::-webkit-outer-spin-button,
.amount-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.amount-input[type=number] {
  -moz-appearance: textfield;
}

.coin-label {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
  font-weight: 500;
  margin-left: 0.5rem;
  flex-shrink: 0;
}

.error-message {
  margin-top: 0.5rem;
  font-size: 0.875rem;
  color: #ef4444;
  text-align: center;
}

/* Slider */
.slider-container {
  margin-bottom: 1.5rem;
  position: relative;
}

.slider {
  width: 100%;
  height: 0.5rem;
  border-radius: 0.25rem;
  outline: none;
  -webkit-appearance: none;
  appearance: none;
  cursor: pointer;
  transition: background 0.2s ease;
}

.slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  background: var(--tg-color-button, #2563eb);
  border: 3px solid white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  cursor: pointer;
  transition: all 0.2s ease;
}

.slider::-webkit-slider-thumb:hover {
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.slider::-moz-range-thumb {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  background: var(--tg-color-button, #2563eb);
  border: 3px solid white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  cursor: pointer;
  transition: all 0.2s ease;
}

.slider::-moz-range-thumb:hover {
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 0.5rem;
  padding: 0 0.75rem;
}

.slider-label {
  font-size: 0.875rem;
  color: var(--tg-color-hint, #6b7280);
  font-weight: 500;
}

/* Quick Bets */
.quick-bets {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.quick-bet-button {
  flex: 1;
  min-width: 3rem;
  padding: 0.75rem 1rem;
  border: 2px solid var(--tg-color-bg, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--tg-color-bg-secondary, white);
  color: var(--tg-color-text, #1f2937);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.quick-bet-button:hover:not(:disabled) {
  border-color: var(--tg-color-button, #2563eb);
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
}

.quick-bet-button.active {
  border-color: var(--tg-color-button, #2563eb);
  background: var(--tg-color-button, #2563eb);
  color: var(--tg-color-button-text, white);
}

.quick-bet-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.quick-bet-button.max-bet {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  border-color: #f59e0b;
  color: white;
}

.quick-bet-button.max-bet:hover:not(:disabled) {
  background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
}

.quick-bet-button.max-bet.active {
  background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
}

/* Bet Summary */
.bet-summary {
  background: var(--tg-color-bg, #f8fafc);
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.summary-row:last-child {
  margin-bottom: 0;
}

.summary-row.percentage {
  padding-top: 0.5rem;
  border-top: 1px solid var(--tg-color-bg, #e5e7eb);
  margin-top: 0.5rem;
}

.summary-label {
  color: var(--tg-color-hint, #6b7280);
  font-size: 0.875rem;
}

.summary-value {
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
  font-size: 0.875rem;
}

/* Risk Indicator */
.risk-indicator {
  margin-top: 1rem;
}

.risk-bar {
  height: 0.5rem;
  background: var(--tg-color-bg, #e5e7eb);
  border-radius: 0.25rem;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.risk-fill {
  height: 100%;
  transition: all 0.3s ease;
  border-radius: 0.25rem;
}

.risk-indicator.low .risk-fill {
  background: linear-gradient(90deg, #10b981 0%, #059669 100%);
}

.risk-indicator.medium .risk-fill {
  background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
}

.risk-indicator.high .risk-fill {
  background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
}

.risk-indicator.extreme .risk-fill {
  background: linear-gradient(90deg, #dc2626 0%, #991b1b 100%);
}

.risk-labels {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.risk-label {
  font-size: 0.875rem;
  font-weight: 500;
}

.risk-indicator.low .risk-label {
  color: #059669;
}

.risk-indicator.medium .risk-label {
  color: #d97706;
}

.risk-indicator.high .risk-label {
  color: #dc2626;
}

.risk-indicator.extreme .risk-label {
  color: #991b1b;
}

.risk-percentage {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--tg-color-text, #1f2937);
}

@media (max-width: 640px) {
  .quick-bets {
    gap: 0.375rem;
  }
  
  .quick-bet-button {
    padding: 0.625rem 0.75rem;
    font-size: 0.8125rem;
    min-width: 2.5rem;
  }
  
  .amount-input {
    font-size: 1rem;
  }
  
  .amount-input-wrapper {
    padding: 0.625rem 0.875rem;
  }
  
  .bet-summary {
    padding: 0.875rem;
  }
}
</style>