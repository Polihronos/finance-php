<script setup lang="ts">
import type { CategoryColor, CategoryIcon } from '@/api/types'
import { CATEGORY_COLORS, CATEGORY_ICONS } from '@/utils/categoryStyle'

const {
  icon,
  color,
  small = false,
} = defineProps<{
  icon: CategoryIcon
  color: CategoryColor
  small?: boolean
}>()
</script>

<template>
  <span class="category-badge" :class="{ small }" :style="{ color: CATEGORY_COLORS[color] }">
    <component :is="CATEGORY_ICONS[icon]" aria-hidden="true" />
  </span>
</template>

<style scoped>
.category-badge {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
}

svg {
  width: 1.75rem;
  height: 1.75rem;
}

.small {
  width: 1.125rem;
  height: 1.125rem;
}

.small svg {
  width: 1.125rem;
  height: 1.125rem;
}
</style>
