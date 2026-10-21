<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { Check, ChevronLeft, ChevronRight, Plus, SquarePen, Trash, X } from '@lucide/vue'

import { ApiError } from '@/api/client'
import AppButton from '@/components/AppButton.vue'
import AppError from '@/components/AppError.vue'
import type { Category } from '@/api/types'
import { useCategoriesStore } from '@/stores/categories'
import { usePeriodStore } from '@/stores/period'
import { formatEur } from '@/utils/money'

const categoriesStore = useCategoriesStore()
const periodStore = usePeriodStore()

const newName = ref('')
const error = ref<string | null>(null)
const loading = ref(false)
const openId = ref<number | null>(null)
const editingId = ref<number | null>(null)
const editName = ref('')
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
  editingId.value = null
  deletingId.value = null
  openId.value = openId.value === categoryId ? null : categoryId
}

function startRename(category: Category): void {
  error.value = null
  editingId.value = category.id
  editName.value = category.name
}

function cancelRename(): void {
  editingId.value = null
}

async function saveRename(categoryId: number): Promise<void> {
  error.value = null
  loading.value = true
  try {
    await categoriesStore.rename(categoryId, editName.value)
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
    await categoriesStore.create(newName.value)
    newName.value = ''
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
      <label>
        New category
        <input v-model="newName" maxlength="100" required />
      </label>
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
        <div class="info">
          <input
            v-if="editingId === category.id"
            v-model="editName"
            class="name"
            aria-label="Category name"
            maxlength="100"
            @click.stop
            @keydown.enter="saveRename(category.id)"
            @keydown.esc="cancelRename"
          />
          <span v-else class="name">{{ category.name }}</span>
          <span class="muted">{{ statsLine(category.id) }}</span>
        </div>
        <span class="muted">{{
          categoriesStore.statsFor(category.id)?.transaction_count ?? 0
        }}</span>
        <div v-if="openId === category.id" class="list-actions" @click.stop>
          <template v-if="editingId === category.id">
            <AppButton :icon="X" label="Cancel" icon-only variant="quiet" @click="cancelRename" />
            <AppButton
              :icon="Check"
              label="Save"
              icon-only
              variant="quiet"
              :disabled="loading"
              @click="saveRename(category.id)"
            />
          </template>
          <template v-else-if="deletingId === category.id">
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
              label="Rename"
              icon-only
              variant="quiet"
              @click="startRename(category)"
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

li {
  display: flex;
  align-items: last baseline;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
  row-gap: 0.125rem;
  min-height: 3.75rem;
  padding: 0.5rem 0.5rem 0.5rem 1rem;
}

span {
  overflow-wrap: anywhere;
}

.info {
  display: contents;
  gap: 0.125rem;
}

.name {
  flex-basis: 100%;
  font-weight: 600;
  text-align: center;
}

.list-actions {
  flex-basis: calc(100% + 1.25rem);
  margin: 0 -0.5rem 0 -0.75rem;
}
</style>
