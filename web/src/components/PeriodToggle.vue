<script setup lang="ts">
import { computed } from 'vue'
import { Moon, Sun } from '@lucide/vue'

import { usePeriodStore } from '@/stores/period'

const periodStore = usePeriodStore()

const isMonth = computed(() => periodStore.period === 'month')
const switchLabel = computed(() => (isMonth.value ? 'Show yearly' : 'Show monthly'))
</script>

<template>
  <div class="period">
    <span class="label">{{ isMonth ? 'Monthly' : 'Yearly' }}</span>
    <button
      type="button"
      :aria-label="switchLabel"
      :title="switchLabel"
      @click="periodStore.toggle"
    >
      <span :class="{ on: isMonth }"><Moon /></span>
      <span :class="{ on: !isMonth }"><Sun /></span>
    </button>
  </div>
</template>

<style scoped>
.period {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.label {
  font-size: 0.8rem;
  font-weight: 600;
  color: color-mix(in srgb, var(--text), transparent 30%);
}

button {
  display: inline-flex;
  padding: 3px;
  border: 1px solid color-mix(in srgb, var(--text), transparent 70%);
  border-radius: 999px;
  background: white;
  cursor: pointer;
}

button:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

button span {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  color: color-mix(in srgb, var(--text), transparent 65%);
  transition:
    color 0.2s,
    background 0.2s;
}

button span.on {
  background: color-mix(in srgb, var(--secondary), transparent 85%);
  color: var(--secondary);
  filter: drop-shadow(0 0 4px color-mix(in srgb, var(--secondary), transparent 35%));
}

svg {
  width: 1.25rem;
  height: 1.25rem;
}
</style>
