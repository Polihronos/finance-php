<script setup lang="ts">
import { useId } from 'vue'

import type { CategoryColor, CategoryIcon } from '@/api/types'
import { CATEGORY_COLORS, CATEGORY_ICONS, COLOR_NAMES, ICON_NAMES } from '@/utils/categoryStyle'

const icon = defineModel<CategoryIcon>('icon', { required: true })
const color = defineModel<CategoryColor>('color', { required: true })

const id = useId()
</script>

<template>
  <div class="style-picker" :style="{ '--chosen': CATEGORY_COLORS[color] }">
    <fieldset class="colors">
      <legend>Color</legend>
      <label
        v-for="name in COLOR_NAMES"
        :key="name"
        class="color"
        :class="{ on: name === color }"
        :style="{ background: CATEGORY_COLORS[name] }"
      >
        <input
          v-model="color"
          type="radio"
          :name="`${id}-color`"
          :value="name"
          :aria-label="name"
        />
      </label>
    </fieldset>
    <fieldset class="icons">
      <legend>Icon</legend>
      <label v-for="name in ICON_NAMES" :key="name" class="icon" :class="{ on: name === icon }">
        <input v-model="icon" type="radio" :name="`${id}-icon`" :value="name" :aria-label="name" />
        <component :is="CATEGORY_ICONS[name]" aria-hidden="true" />
      </label>
    </fieldset>
  </div>
</template>

<style scoped>
.style-picker {
  display: grid;
  gap: 1rem;
}

.colors {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.icons {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.375rem;
}

label {
  position: relative;
  cursor: pointer;
}

input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}

.color {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
}

.icon {
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  border-radius: 0.5rem;
  color: color-mix(in srgb, var(--text), transparent 55%);
}

.icon svg {
  width: 1.5rem;
  height: 1.5rem;
}

.color.on,
.color:has(:focus-visible),
.icon:has(:focus-visible) {
  outline: 2px solid var(--text);
  outline-offset: 2px;
}

.icon.on {
  color: var(--chosen);
}

.icon.on svg {
  width: 2rem;
  height: 2rem;
  stroke-width: 2.5;
}
</style>
