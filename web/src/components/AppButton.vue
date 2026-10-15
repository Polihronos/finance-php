<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue'

const {
  icon,
  label,
  iconOnly = false,
  type = 'button',
  variant = 'primary',
} = defineProps<{
  icon: LucideIcon
  label: string
  iconOnly?: boolean
  type?: 'button' | 'submit'
  variant?: 'primary' | 'quiet'
}>()
</script>

<template>
  <button
    :type="type"
    :class="variant"
    :aria-label="iconOnly ? label : undefined"
    :title="iconOnly ? label : undefined"
  >
    <component :is="icon" />
    <span v-if="!iconOnly">{{ label }}</span>
  </button>
</template>

<style scoped>
button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0 1.25rem;
  border: 0;
  border-radius: 0.5rem;
  background: var(--primary);
  color: var(--background);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

button[aria-label] {
  width: 2.75rem;
  padding: 0;
}

button:hover:not(:disabled) {
  background: color-mix(in srgb, var(--primary), black 15%);
}

button:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

svg {
  flex-shrink: 0;
  width: 1.25rem;
  height: 1.25rem;
}

.quiet {
  background: transparent;
  color: var(--text);
}

.quiet:hover:not(:disabled) {
  background: color-mix(in srgb, var(--text), transparent 92%);
}
</style>
