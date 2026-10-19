import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import { ApiError } from '@/api/client'
import type { Transaction, TransactionInput } from '@/api/types'
import { useTransactionsStore } from '../transactions'

function transaction(id: number, date: string, description: string): Transaction {
  return {
    id,
    category_id: 1,
    amount: '12.50',
    currency: 'EUR',
    date,
    payment_method: 'card',
    description,
    place: null,
  }
}

const lunch = transaction(2, '2026-10-12', 'Lunch')
const gas = transaction(1, '2026-10-01', 'Gas')
const listBody = JSON.stringify({ transactions: [lunch, gas] })

const input: TransactionInput = {
  category_id: 1,
  amount: 12.5,
  date: '2026-10-05',
  payment_method: 'card',
  description: 'Coffee',
  place: null,
}

// Answers each "METHOD /path" with its own status and body
function fakeApi(answers: Record<string, [number, string]>) {
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const [status, body] = answers[`${init?.method} ${input}`] ?? [404, '{"error":"Not found"}']
    return new Response(body, { status })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function calledPaths(fetchMock: ReturnType<typeof fakeApi>): string[] {
  return fetchMock.mock.calls.map(([input, init]) => `${init?.method} ${input}`)
}

function ids(transactions: Transaction[]): number[] {
  return transactions.map((row) => row.id)
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.unstubAllGlobals()
})

// Load: fetches the list, newest first as the API sends it

test('load fills the transactions', async () => {
  fakeApi({ 'GET /api/transactions': [200, listBody] })
  const transactions = useTransactionsStore()

  await transactions.load()

  expect(transactions.transactions).toStrictEqual([lunch, gas])
})

test('failed load keeps the list unchanged', async () => {
  fakeApi({ 'GET /api/transactions': [500, 'Internal Server Error'] })
  const transactions = useTransactionsStore()

  const promise = transactions.load()

  await expect(promise).rejects.toMatchObject({ status: 500, message: 'request failed (500)' })
  expect(transactions.transactions).toStrictEqual([])
})

// Create: one request, the saved transaction goes into the list at its date

test('create adds the saved transaction without loading again', async () => {
  const coffee = transaction(3, '2026-10-05', 'Coffee')
  const fetchMock = fakeApi({
    'GET /api/transactions': [200, listBody],
    'POST /api/transactions': [201, JSON.stringify({ success: true, transaction: coffee })],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.create(input)

  expect(calledPaths(fetchMock)).toStrictEqual(['GET /api/transactions', 'POST /api/transactions'])
  expect(transactions.transactions).toStrictEqual([lunch, coffee, gas])
})

test('create on the same date puts the newest first', async () => {
  const dinner = transaction(3, '2026-10-12', 'Dinner')
  fakeApi({
    'GET /api/transactions': [200, listBody],
    'POST /api/transactions': [201, JSON.stringify({ success: true, transaction: dinner })],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.create({ ...input, date: '2026-10-12', description: 'Dinner' })

  expect(ids(transactions.transactions)).toStrictEqual([3, 2, 1])
})

test('failed create keeps the list unchanged', async () => {
  fakeApi({
    'GET /api/transactions': [200, listBody],
    'POST /api/transactions': [400, '{"error":"category not found"}'],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  const promise = transactions.create(input)

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 400, message: 'category not found' })
  expect(transactions.transactions).toStrictEqual([lunch, gas])
})

// Update: one request, the saved transaction replaces the old one and moves to its new date

test('update replaces the transaction and moves it to its date', async () => {
  const movedGas = transaction(1, '2026-10-20', 'Gas')
  const fetchMock = fakeApi({
    'GET /api/transactions': [200, listBody],
    'PUT /api/transactions/1': [200, JSON.stringify({ success: true, transaction: movedGas })],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.update(1, { ...input, date: '2026-10-20', description: 'Gas' })

  expect(calledPaths(fetchMock)).toStrictEqual(['GET /api/transactions', 'PUT /api/transactions/1'])
  expect(transactions.transactions).toStrictEqual([movedGas, lunch])
})

test('failed update keeps the list unchanged', async () => {
  fakeApi({
    'GET /api/transactions': [200, listBody],
    'PUT /api/transactions/1': [404, '{"error":"transaction not found"}'],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  const promise = transactions.update(1, input)

  await expect(promise).rejects.toMatchObject({ status: 404, message: 'transaction not found' })
  expect(transactions.transactions).toStrictEqual([lunch, gas])
})

// Remove: one request, the transaction leaves the list

test('remove takes the transaction out without loading again', async () => {
  const fetchMock = fakeApi({
    'GET /api/transactions': [200, listBody],
    'DELETE /api/transactions/2': [200, '{"success":true}'],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.remove(2)

  expect(calledPaths(fetchMock)).toStrictEqual([
    'GET /api/transactions',
    'DELETE /api/transactions/2',
  ])
  expect(transactions.transactions).toStrictEqual([gas])
})

test('failed remove keeps the list unchanged', async () => {
  fakeApi({
    'GET /api/transactions': [200, listBody],
    'DELETE /api/transactions/2': [404, '{"error":"transaction not found"}'],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  const promise = transactions.remove(2)

  await expect(promise).rejects.toMatchObject({ status: 404, message: 'transaction not found' })
  expect(transactions.transactions).toStrictEqual([lunch, gas])
})
