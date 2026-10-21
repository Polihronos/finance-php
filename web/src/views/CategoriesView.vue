<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { Check, ChevronLeft, ChevronRight, Plus, SquarePen, Trash, X } from '@lucide/vue'

import { ApiError } from '@/api/client'
import AppButton from '@/components/AppButton.vue'
import AppError from '@/components/AppError.vue'
import CategoryBadge from '@/components/CategoryBadge.vue'
import CategoryStylePicker from '@/components/CategoryStylePicker.vue'
import type { Category, CategoryColor, CategoryIcon } from '@/api/types'
import { useCategoriesStore } from '@/stores/categories'
import { usePeriodStore } from '@/stores/period'
import { DEFAULT_COLOR, DEFAULT_ICON } from '@/utils/categoryStyle'
import { formatEur } from '@/utils/money'

const categoriesStore = useCategoriesStore()
const periodStore = usePeriodStore()

const newName = ref('')
const newIcon = ref<CategoryIcon>(DEFAULT_ICON)
const newColor = ref<CategoryColor>(DEFAULT_COLOR)
const newPickerOpen = ref(false)
const error = ref<string | null>(null)
const loading = ref(false)
const openId = ref<number | null>(null)
const editingId = ref<number | null>(null)
const editName = ref('')
const editIcon = ref<CategoryIcon>(DEFAULT_ICON)
const editColor = ref<CategoryColor>(DEFAULT_COLOR)
const editPickerOpen = ref(false)
const deletingId = ref<number | null>(null)

function showError(e: unknown): void {
  error.value = e instanceof ApiError ? e.message : 'cannot reach the server'
}

function statsLine(categoryId: number): string {
  const stats = categoriesStore.statsFor(categoryId)
  return stats === undefined ? '' : formatEur(stats.total)
}

watch(
  () => periodStore.statsPeriod,
  async () => {
    try {
      await categoriesStore.loadStats()
    } catch (e) {
      showError(e)
    }
  },
)

function toggleOpen(categoryId: number): void {
  if (editingId.value === categoryId) {
    return
  }
  editingId.value = null
  deletingId.value = null
  openId.value = openId.value === categoryId ? null : categoryId
}

function startEdit(category: Category): void {
  error.value = null
  editingId.value = category.id
  editName.value = category.name
  editIcon.value = category.icon
  editColor.value = category.color
  editPickerOpen.value = false
}

function cancelEdit(): void {
  editingId.value = null
}

async function saveEdit(categoryId: number): Promise<void> {
  error.value = null
  loading.value = true
  try {
    await categoriesStore.update(categoryId, {
      name: editName.value,
      icon: editIcon.value,
      color: editColor.value,
    })
    editingId.value = null
  } catch (e) {
    showError(e)
  } finally {
    loading.value = false
  }
}

function startDelete(categoryId: number): void {
  error.value = null
  deletingId.value = categoryId
}

function cancelDelete(): void {
  deletingId.value = null
}

async function confirmDelete(categoryId: number): Promise<void> {
  error.value = null
  loading.value = true
  try {
    await categoriesStore.remove(categoryId)
    openId.value = null
  } catch (e) {
    showError(e)
  } finally {
    deletingId.value = null
    loading.value = false
  }
}

onMounted(async () => {
  try {
    await categoriesStore.load()
  } catch (e) {
    showError(e)
  }
})

async function add(): Promise<void> {
  error.value = null
  loading.value = true
  try {
    await categoriesStore.create({
      name: newName.value,
      icon: newIcon.value,
      color: newColor.value,
    })
    newName.value = ''
    newIcon.value = DEFAULT_ICON
    newColor.value = DEFAULT_COLOR
    newPickerOpen.value = false
  } catch (e) {
    showError(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main>
    <h1>Categories</h1>
    <form @submit.prevent="add">
      <div class="field">
        <label for="new-category">New category</label>
        <div class="name-row">
          <button
            type="button"
            class="style-button"
            aria-label="Choose icon and color"
            :aria-expanded="newPickerOpen"
            @click="newPickerOpen = !newPickerOpen"
          >
            <CategoryBadge :icon="newIcon" :color="newColor" />
          </button>
          <input id="new-category" v-model="newName" maxlength="100" required />
        </div>
      </div>
      <CategoryStylePicker v-if="newPickerOpen" v-model:icon="newIcon" v-model:color="newColor" />
      <AppButton :icon="Plus" label="Add" type="submit" :disabled="loading" />
    </form>
    <AppError v-if="error">{{ error }}</AppError>
    <div class="picker">
      <AppButton
        :icon="ChevronLeft"
        :label="periodStore.period === 'month' ? 'Previous month' : 'Previous year'"
        icon-only
        variant="quiet"
        @click="periodStore.shift(-1)"
      />
      <span>{{ periodStore.label }}</span>
      <AppButton
        :icon="ChevronRight"
        :label="periodStore.period === 'month' ? 'Next month' : 'Next year'"
        icon-only
        variant="quiet"
        :disabled="periodStore.atLatest"
        @click="periodStore.shift(1)"
      />
    </div>
    <ul class="list">
      <li
        v-for="category in categoriesStore.categories"
        :key="category.id"
        class="list-item"
        tabindex="0"
        @click="toggleOpen(category.id)"
        @keydown.enter.self="toggleOpen(category.id)"
      >
        <template v-if="editingId === category.id">
          <div class="name-row wide-row" @click.stop>
            <button
              type="button"
              class="style-button"
              aria-label="Choose icon and color"
              :aria-expanded="editPickerOpen"
              @click="editPickerOpen = !editPickerOpen"
            >
              <CategoryBadge :icon="editIcon" :color="editColor" />
            </button>
            <input
              v-model="editName"
              aria-label="Category name"
              maxlength="100"
              @keydown.enter="saveEdit(category.id)"
              @keydown.esc="cancelEdit"
            />
            <AppButton :icon="X" label="Cancel" icon-only variant="quiet" @click="cancelEdit" />
            <AppButton
              :icon="Check"
              label="Save"
              icon-only
              variant="quiet"
              :disabled="loading"
              @click="saveEdit(category.id)"
            />
          </div>
          <CategoryStylePicker
            v-if="editPickerOpen"
            v-model:icon="editIcon"
            v-model:color="editColor"
            class="wide-row"
            @click.stop
          />
        </template>
        <template v-else>
          <CategoryBadge class="card-badge" :icon="category.icon" :color="category.color" />
          <span class="name">{{ category.name }}</span>
          <span class="count muted">{{
            categoriesStore.statsFor(category.id)?.transaction_count ?? 0
          }}</span>
          <span class="muted">{{ statsLine(category.id) }}</span>
        </template>
        <div
          v-if="openId === category.id && editingId !== category.id"
          class="list-actions"
          @click.stop
        >
          <template v-if="deletingId === category.id">
            <AppButton :icon="X" label="Cancel" icon-only variant="quiet" @click="cancelDelete" />
            <span>Delete {{ category.name }}?</span>
            <AppButton
              :icon="Trash"
              label="Delete"
              icon-only
              variant="quiet"
              :disabled="loading"
              @click="confirmDelete(category.id)"
            />
          </template>
          <template v-else>
            <AppButton
              :icon="SquarePen"
              label="Edit"
              icon-only
              variant="quiet"
              @click="startEdit(category)"
            />
            <AppButton
              :icon="Trash"
              label="Delete"
              icon-only
              variant="quiet"
              @click="startDelete(category.id)"
            />
          </template>
        </div>
      </li>
    </ul>
  </main>
</template>

<style scoped>
form {
  margin-bottom: 1rem;
}

.picker {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 1.5rem 0 0.5rem;
  font-weight: 600;
}

.field {
  display: grid;
  gap: 0.25rem;
}

.name-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.name-row input {
  flex: 1;
  min-width: 0;
}

.style-button {
  display: flex;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: none;
  cursor: pointer;
}

.style-button:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

li {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.125rem 0.75rem;
  min-height: 3.75rem;
  padding: 0.5rem 1rem 0.5rem 0.5rem;
}

span {
  overflow-wrap: anywhere;
}

.card-badge,
.count {
  grid-row: span 2;
}

.name {
  font-weight: 600;
}

.wide-row {
  grid-column: 1 / -1;
}

.list-actions {
  grid-column: 1 / -1;
  margin: 0 -0.75rem 0 -0.25rem;
}
</style>
