<script setup lang="ts">
import { ref, useId, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Banknote, Check, CreditCard, Plus, X } from '@lucide/vue'

import { ApiError } from '@/api/client'
import { suggestTransactions } from '@/api/transactions'
import type { PaymentMethod, Suggestion, Transaction } from '@/api/types'
import AppButton from '@/components/AppButton.vue'
import AppError from '@/components/AppError.vue'
import { useCategoriesStore } from '@/stores/categories'
import { useTransactionsStore } from '@/stores/transactions'
import { CATEGORY_COLORS, CATEGORY_ICONS } from '@/utils/categoryStyle'

const { transaction } = defineProps<{ transaction?: Transaction }>()
const emit = defineEmits<{ close: [] }>()

const router = useRouter()
const transactionsStore = useTransactionsStore()
const categoriesStore = useCategoriesStore()

function today(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

const listId = useId()
const description = ref(transaction?.description ?? '')
const amount = ref(transaction?.amount ?? '')
const categoryId = ref<number | ''>(transaction?.category_id ?? '')
const paymentMethod = ref<PaymentMethod>(transaction?.payment_method ?? 'card')
const date = ref(transaction?.date ?? today())
const place = ref(transaction?.place ?? '')
const suggestions = ref<Suggestion[]>([])
const error = ref<string | null>(null)
const loading = ref(false)

watch(amount, (text) => {
  amount.value = text.replace(/[^\d.,]/g, '')
})

let timer: ReturnType<typeof setTimeout> | undefined
let latestQuery = ''

function fillFromSuggestion(query: string): boolean {
  const picked = suggestions.value.find(
    (suggestion) => suggestion.description.toLowerCase() === query.toLowerCase(),
  )
  if (!picked) {
    return false
  }
  description.value = picked.description
  categoryId.value = picked.category_id
  place.value = picked.place ?? ''
  return true
}

watch(description, (text) => {
  clearTimeout(timer)
  const query = text.trim()

  if (fillFromSuggestion(query)) {
    return
  }

  latestQuery = query
  if (query === '') {
    suggestions.value = []
    return
  }

  timer = setTimeout(async () => {
    try {
      const answer = await suggestTransactions(query)
      if (query === latestQuery) {
        suggestions.value = answer.suggestions
        fillFromSuggestion(query)
      }
    } catch {
      suggestions.value = []
    }
  }, 200)
})

async function save(): Promise<void> {
  error.value = null
  loading.value = true
  try {
    const input = {
      category_id: Number(categoryId.value),
      amount: Number(amount.value.replace(',', '.')),
      date: date.value,
      payment_method: paymentMethod.value,
      description: description.value.trim() || null,
      place: place.value.trim() || null,
    }
    if (transaction) {
      await transactionsStore.update(transaction.id, input)
    } else {
      await transactionsStore.create(input)
    }
    emit('close')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'cannot reach the server'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form @submit.prevent="save">
    <label>
      Description
      <input v-model="description" :list="listId" maxlength="255" autocomplete="off" />
      <datalist :id="listId">
        <option
          v-for="suggestion in suggestions"
          :key="suggestion.description"
          :value="suggestion.description"
        />
      </datalist>
    </label>
    <label>
      Amount
      <input
        v-model="amount"
        inputmode="decimal"
        pattern="\d+([.,]\d{1,2})?"
        title="A number with up to 2 decimals"
        autocomplete="off"
        required
      />
    </label>
    <fieldset v-if="categoriesStore.categories.length > 0" class="choices categories">
      <legend>Category</legend>
      <label
        v-for="category in categoriesStore.categories"
        :key="category.id"
        class="category"
        :style="{ '--choice': CATEGORY_COLORS[category.color] }"
      >
        <input v-model="categoryId" type="radio" name="category" :value="category.id" required />
        <component :is="CATEGORY_ICONS[category.icon]" aria-hidden="true" />
        {{ category.name }}
      </label>
    </fieldset>
    <div v-else class="no-categories">
      <span>Category</span>
      <AppButton :icon="Plus" label="Add category" @click="router.push({ name: 'categories' })" />
    </div>
    <fieldset class="choices">
      <legend>Paid with</legend>
      <label>
        <input v-model="paymentMethod" type="radio" name="payment_method" value="card" />
        <CreditCard />
        Card
      </label>
      <label class="cash">
        <input v-model="paymentMethod" type="radio" name="payment_method" value="cash" />
        <Banknote />
        Cash
      </label>
    </fieldset>
    <label>
      Date
      <input v-model="date" type="date" required />
    </label>
    <label>
      Place
      <input v-model="place" maxlength="255" />
    </label>
    <AppError v-if="error">{{ error }}</AppError>
    <div class="actions">
      <AppButton :icon="X" label="Cancel" variant="quiet" @click="emit('close')" />
      <AppButton :icon="Check" label="Save" type="submit" :disabled="loading" />
    </div>
  </form>
</template>

<style scoped>
.categories {
  grid-template-columns: repeat(auto-fill, minmax(6.5rem, 1fr));
}

.category svg {
  color: var(--choice);
}

.category:has(:checked) {
  border-color: var(--choice);
  background: color-mix(in srgb, var(--choice), transparent 90%);
  color: var(--choice);
}

.cash:has(:checked) {
  border-color: var(--secondary);
  background: color-mix(in srgb, var(--secondary), transparent 92%);
  color: var(--secondary);
}

.no-categories {
  display: grid;
  gap: 0.25rem;
  font-weight: 500;
}

.actions {
  display: flex;
  justify-content: space-between;
}
</style>
