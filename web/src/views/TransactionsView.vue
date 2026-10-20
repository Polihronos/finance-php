<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Banknote, ChevronsDown, CreditCard, Plus, SquarePen, Trash, X } from '@lucide/vue'

import { ApiError } from '@/api/client'
import AppButton from '@/components/AppButton.vue'
import AppError from '@/components/AppError.vue'
import TransactionForm from '@/components/TransactionForm.vue'
import type { Transaction } from '@/api/types'
import { useCategoriesStore } from '@/stores/categories'
import { useTransactionsStore } from '@/stores/transactions'
import { formatEur } from '@/utils/money'

const MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
]
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const transactionsStore = useTransactionsStore()
const categoriesStore = useCategoriesStore()

const error = ref<string | null>(null)
const loaded = ref(false)
const adding = ref(false)
const loading = ref(false)
const loadingOlder = ref(false)
const olderError = ref<string | null>(null)
const openId = ref<number | null>(null)
const editingId = ref<number | null>(null)
const deletingId = ref<number | null>(null)
const thisYear = new Date().getFullYear()

const categoryNames = computed(
  () => new Map(categoriesStore.categories.map((category) => [category.id, category.name])),
)

const months = computed(() => {
  const groups: { key: string; cents: number; rows: Transaction[] }[] = []
  for (const row of transactionsStore.transactions) {
    const key = row.date.slice(0, 7)
    const last = groups.at(-1)
    if (last?.key === key) {
      last.rows.push(row)
      last.cents += toCents(row.amount)
    } else {
      groups.push({ key, cents: toCents(row.amount), rows: [row] })
    }
  }
  return groups
})

function monthLabel(key: string): string {
  const year = Number(key.slice(0, 4))
  const month = MONTHS[Number(key.slice(5, 7)) - 1]
  return year === thisYear ? `${month}` : `${month} ${year}`
}

function dayNumber(date: string): number {
  return Number(date.slice(8, 10))
}

function weekday(date: string): string {
  return WEEKDAYS[new Date(`${date}T00:00:00Z`).getUTCDay()] ?? ''
}

function toCents(amount: string): number {
  return Math.round(Number(amount) * 100)
}

function centsLabel(cents: number): string {
  return formatEur((cents / 100).toFixed(2))
}

function categoryName(categoryId: number): string {
  return categoryNames.value.get(categoryId) ?? ''
}

function title(row: Transaction): string {
  return row.description ?? categoryName(row.category_id)
}

async function loadOlder(): Promise<void> {
  olderError.value = null
  loadingOlder.value = true
  try {
    await transactionsStore.loadOlder()
  } catch (e) {
    olderError.value = e instanceof ApiError ? e.message : 'cannot reach the server'
  } finally {
    loadingOlder.value = false
  }
}

function showError(e: unknown): void {
  error.value = e instanceof ApiError ? e.message : 'cannot reach the server'
}

function toggleOpen(id: number): void {
  editingId.value = null
  deletingId.value = null
  openId.value = openId.value === id ? null : id
}

function startEdit(id: number): void {
  error.value = null
  editingId.value = id
}

function closeEdit(): void {
  editingId.value = null
  openId.value = null
}

function startDelete(id: number): void {
  error.value = null
  deletingId.value = id
}

function cancelDelete(): void {
  deletingId.value = null
}

async function confirmDelete(id: number): Promise<void> {
  error.value = null
  loading.value = true
  try {
    await transactionsStore.remove(id)
    openId.value = null
  } catch (e) {
    showError(e)
  } finally {
    deletingId.value = null
    loading.value = false
  }
}

function details(row: Transaction): string {
  return [categoryName(row.category_id), row.place].filter((part) => part).join(' | ')
}

onMounted(async () => {
  try {
    await Promise.all([transactionsStore.load(), categoriesStore.load()])
  } catch (e) {
    showError(e)
  } finally {
    loaded.value = true
  }
})
</script>

<template>
  <main>
    <h1>Transactions</h1>
    <TransactionForm v-if="adding" @close="adding = false" />
    <AppButton v-else class="wide" :icon="Plus" label="Add transaction" @click="adding = true" />
    <AppError v-if="error">{{ error }}</AppError>
    <p v-else-if="loaded && months.length === 0" class="empty">
      {{
        transactionsStore.hasOlder ? 'No transactions in the last 3 months' : 'No transactions yet'
      }}
    </p>
    <section v-for="month in months" :key="month.key">
      <div class="divider">
        <span>{{ monthLabel(month.key) }}</span>
        <span class="divider-total">{{ centsLabel(month.cents) }}</span>
      </div>
      <ul class="list">
        <li
          v-for="row in month.rows"
          :key="row.id"
          class="list-item"
          :class="{ editing: editingId === row.id }"
          tabindex="0"
          @click="toggleOpen(row.id)"
          @keydown.enter.self="toggleOpen(row.id)"
        >
          <TransactionForm
            v-if="editingId === row.id"
            :transaction="row"
            @close="closeEdit"
            @click.stop
          />
          <template v-else>
            <span class="badge">
              <b>{{ dayNumber(row.date) }}</b>
              <small>{{ weekday(row.date) }}</small>
            </span>
            <span class="name">{{ title(row) }}</span>
            <span class="amount">{{ formatEur(row.amount) }}</span>
            <span class="details muted">{{ details(row) }}</span>
            <component
              :is="row.payment_method === 'cash' ? Banknote : CreditCard"
              :class="['method', row.payment_method]"
              :size="20"
              role="img"
              :aria-label="row.payment_method"
            />
            <div v-if="openId === row.id" class="list-actions" @click.stop>
              <template v-if="deletingId === row.id">
                <AppButton
                  :icon="X"
                  label="Cancel"
                  icon-only
                  variant="quiet"
                  @click="cancelDelete"
                />
                <span>Delete {{ title(row) }}?</span>
                <AppButton
                  :icon="Trash"
                  label="Delete"
                  icon-only
                  variant="quiet"
                  :disabled="loading"
                  @click="confirmDelete(row.id)"
                />
              </template>
              <template v-else>
                <AppButton
                  :icon="SquarePen"
                  label="Edit"
                  icon-only
                  variant="quiet"
                  @click="startEdit(row.id)"
                />
                <AppButton
                  :icon="Trash"
                  label="Delete"
                  icon-only
                  variant="quiet"
                  @click="startDelete(row.id)"
                />
              </template>
            </div>
          </template>
        </li>
      </ul>
    </section>
    <AppButton
      v-if="loaded && transactionsStore.hasOlder"
      class="wide older"
      :icon="ChevronsDown"
      label="Load 3 more months"
      variant="quiet"
      :disabled="loadingOlder"
      @click="loadOlder"
    />
    <AppError v-if="olderError">{{ olderError }}</AppError>
  </main>
</template>

<style scoped>
.older {
  margin-top: 1.5rem;
  border: 1px dashed color-mix(in srgb, var(--text), transparent 70%);
}

li {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 0.125rem 0.5rem;
  padding: 0.5rem 1rem 0.5rem 0.5rem;
}

.badge {
  display: grid;
  grid-row: span 2;
  place-content: center;
  width: 2.75rem;
  border-radius: 0.375rem;
  background: color-mix(in srgb, var(--text), transparent 92%);
  line-height: 1.1;
  text-align: center;
}

.badge b {
  font-size: 1.1rem;
}

.badge small {
  font-size: 0.7rem;
  color: color-mix(in srgb, var(--text), transparent 35%);
}

li.editing {
  display: block;
  padding: 1rem;
  cursor: default;
}

.name {
  font-weight: 600;
  overflow-wrap: anywhere;
}

.amount {
  font-weight: 600;
  white-space: nowrap;
}

.details {
  overflow-wrap: anywhere;
}

.method {
  justify-self: end;
}

.method.card {
  color: var(--primary);
}

.method.cash {
  color: var(--secondary);
}

.list-actions {
  grid-column: 1 / -1;
  margin: 0 -0.75rem 0 -0.25rem;
}
</style>
