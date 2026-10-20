import { ref } from 'vue'
import { defineStore } from 'pinia'

import type { Transaction, TransactionInput } from '@/api/types'
import * as transactionsApi from '@/api/transactions'

const MONTHS_PER_LOAD = 3
const NO_END = '9999-12-31'

function monthStart(year: number, month: number): string {
  return new Date(Date.UTC(year, month, 1)).toISOString().slice(0, 10)
}

function newestFirst(a: Transaction, b: Transaction): number {
  if (a.date !== b.date) {
    return a.date < b.date ? 1 : -1
  }
  return b.id - a.id
}

export const useTransactionsStore = defineStore('transactions', () => {
  const transactions = ref<Transaction[]>([])
  const hasOlder = ref(false)
  const loadedFrom = ref<string | null>(null)

  async function load(): Promise<void> {
    const now = new Date()
    const from = monthStart(now.getFullYear(), now.getMonth() - (MONTHS_PER_LOAD - 1))
    const answer = await transactionsApi.listTransactions({ from, to: NO_END })
    transactions.value = answer.transactions
    hasOlder.value = answer.has_older
    loadedFrom.value = from
  }

  async function loadOlder(): Promise<void> {
    if (loadedFrom.value === null) {
      return
    }
    const to = loadedFrom.value
    const from = monthStart(Number(to.slice(0, 4)), Number(to.slice(5, 7)) - 1 - MONTHS_PER_LOAD)
    const answer = await transactionsApi.listTransactions({ from, to })
    transactions.value = [...transactions.value, ...answer.transactions]
    hasOlder.value = answer.has_older
    loadedFrom.value = from
  }

  function isLoaded(transaction: Transaction): boolean {
    return loadedFrom.value === null || transaction.date >= loadedFrom.value
  }

  async function create(input: TransactionInput): Promise<void> {
    const answer = await transactionsApi.createTransaction(input)
    if (isLoaded(answer.transaction)) {
      transactions.value = [...transactions.value, answer.transaction].sort(newestFirst)
    } else {
      hasOlder.value = true
    }
  }

  async function update(id: number, input: TransactionInput): Promise<void> {
    const answer = await transactionsApi.updateTransaction(id, input)
    const others = transactions.value.filter((row) => row.id !== id)
    if (isLoaded(answer.transaction)) {
      transactions.value = [...others, answer.transaction].sort(newestFirst)
    } else {
      transactions.value = others
      hasOlder.value = true
    }
  }

  async function remove(id: number): Promise<void> {
    await transactionsApi.deleteTransaction(id)
    transactions.value = transactions.value.filter((row) => row.id !== id)
  }

  return { transactions, hasOlder, load, loadOlder, create, update, remove }
})
