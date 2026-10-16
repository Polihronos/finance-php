<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Check, Plus, SquarePen, Trash, X } from '@lucide/vue'

import { ApiError } from '@/api/client'
import AppButton from '@/components/AppButton.vue'
import AppError from '@/components/AppError.vue'
import type { Category } from '@/api/types'
import { useCategoriesStore } from '@/stores/categories'
import { usePeriodStore } from '@/stores/period'

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
  if (stats === undefined) {
    return ''
  }

  const monthly = Number(stats.monthly_average)
  const isMonth = periodStore.period === 'month'
  const amount = Math.round(isMonth ? monthly : monthly * 12)

  return `${amount} EUR`
}

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
    <ul>
      <li
        v-for="category in categoriesStore.categories"
        :key="category.id"
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
          <span class="stats">{{ statsLine(category.id) }}</span>
        </div>
        <span class="stats">{{
          categoriesStore.statsFor(category.id)?.transaction_count ?? 0
        }}</span>
        <div v-if="openId === category.id" class="actions" @click.stop>
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

ul {
  display: grid;
  gap: 0.5rem;
  margin: 1.5rem 0 0;
  padding: 0;
  list-style: none;
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
  border: 1px solid color-mix(in srgb, var(--text), transparent 70%);
  border-radius: 0.5rem;
  background: white;
  cursor: pointer;
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

.stats {
  font-size: 0.85rem;
  color: color-mix(in srgb, var(--text), transparent 35%);
}

li:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

.actions {
  display: flex;
  flex-basis: calc(100% + 1.25rem);
  align-items: center;
  justify-content: space-between;
  margin: 0 -0.5rem 0 -0.75rem;
}
</style>
