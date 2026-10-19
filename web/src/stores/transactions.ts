import { ref } from 'vue'
import { defineStore } from 'pinia'

import type { Transaction, TransactionInput } from '@/api/types'
import * as transactionsApi from '@/api/transactions'

function newestFirst(a: Transaction, b: Transaction): number {
  if (a.date !== b.date) {
    return a.date < b.date ? 1 : -1
  }
  return b.id - a.id
}

export const useTransactionsStore = defineStore('transactions', () => {
  const transactions = ref<Transaction[]>([])

  async function load(): Promise<void> {
    const answer = await transactionsApi.listTransactions()
    transactions.value = answer.transactions
  }

  async function create(input: TransactionInput): Promise<void> {
    const answer = await transactionsApi.createTransaction(input)
    transactions.value = [...transactions.value, answer.transaction].sort(newestFirst)
  }

  async function update(id: number, input: TransactionInput): Promise<void> {
    const answer = await transactionsApi.updateTransaction(id, input)
    transactions.value = transactions.value
      .map((row) => (row.id === id ? answer.transaction : row))
      .sort(newestFirst)
  }

  async function remove(id: number): Promise<void> {
    await transactionsApi.deleteTransaction(id)
    transactions.value = transactions.value.filter((row) => row.id !== id)
  }

  return { transactions, load, create, update, remove }
})
