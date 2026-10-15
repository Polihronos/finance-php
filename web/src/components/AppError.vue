<script setup lang="ts">
import { Ghost } from '@lucide/vue'
</script>

<template>
  <p role="alert">
    <Ghost />
    <slot />
  </p>
</template>

<style scoped>
p {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin: 0;
  padding: 0.625rem 0.875rem;
  border-left: 4px solid var(--accent);
  border-radius: 0.25rem 0.75rem 0.75rem 0.25rem;
  background: color-mix(in srgb, var(--accent), transparent 75%);
  color: var(--text);
  font-size: 0.9rem;
  text-align: center;
}

svg {
  flex-shrink: 0;
  width: 1.125rem;
  height: 1.125rem;
}
</style>
