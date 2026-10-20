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
const listBody = JSON.stringify({ transactions: [lunch, gas], has_older: true })
const LIST = 'GET /api/transactions?from=2026-08-01&to=9999-12-31'

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
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 9, 19))
})

afterEach(() => {
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

// Load: fetches this month and the 2 before it, with no end so future dates show too

test('load fills the transactions of the last 3 months', async () => {
  const fetchMock = fakeApi({ [LIST]: [200, listBody] })
  const transactions = useTransactionsStore()

  await transactions.load()

  expect(calledPaths(fetchMock)).toStrictEqual([LIST])
  expect(transactions.transactions).toStrictEqual([lunch, gas])
  expect(transactions.hasOlder).toBe(true)
})

test('failed load keeps the list unchanged', async () => {
  fakeApi({ [LIST]: [500, 'Internal Server Error'] })
  const transactions = useTransactionsStore()

  const promise = transactions.load()

  await expect(promise).rejects.toMatchObject({ status: 500, message: 'request failed (500)' })
  expect(transactions.transactions).toStrictEqual([])
})

// Load older: each press adds the 3 months before the oldest loaded month at the end

test('loadOlder adds the 3 months before', async () => {
  const may = transaction(4, '2026-05-20', 'Shoes')
  const fetchMock = fakeApi({
    [LIST]: [200, listBody],
    'GET /api/transactions?from=2026-05-01&to=2026-08-01': [
      200,
      JSON.stringify({ transactions: [may], has_older: false }),
    ],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.loadOlder()

  expect(calledPaths(fetchMock)).toStrictEqual([
    LIST,
    'GET /api/transactions?from=2026-05-01&to=2026-08-01',
  ])
  expect(transactions.transactions).toStrictEqual([lunch, gas, may])
  expect(transactions.hasOlder).toBe(false)
})

test('loadOlder goes back over the new year', async () => {
  const empty = JSON.stringify({ transactions: [], has_older: true })
  const fetchMock = fakeApi({
    [LIST]: [200, listBody],
    'GET /api/transactions?from=2026-05-01&to=2026-08-01': [200, empty],
    'GET /api/transactions?from=2026-02-01&to=2026-05-01': [200, empty],
    'GET /api/transactions?from=2025-11-01&to=2026-02-01': [200, empty],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.loadOlder()
  await transactions.loadOlder()
  await transactions.loadOlder()

  expect(calledPaths(fetchMock)).toStrictEqual([
    LIST,
    'GET /api/transactions?from=2026-05-01&to=2026-08-01',
    'GET /api/transactions?from=2026-02-01&to=2026-05-01',
    'GET /api/transactions?from=2025-11-01&to=2026-02-01',
  ])
})

test('failed loadOlder keeps the list unchanged', async () => {
  fakeApi({
    [LIST]: [200, listBody],
    'GET /api/transactions?from=2026-05-01&to=2026-08-01': [500, 'Internal Server Error'],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  const promise = transactions.loadOlder()

  await expect(promise).rejects.toMatchObject({ status: 500 })
  expect(transactions.transactions).toStrictEqual([lunch, gas])
  expect(transactions.hasOlder).toBe(true)
})

// Create: one request, the saved transaction goes into the list at its date

test('create adds the saved transaction without loading again', async () => {
  const coffee = transaction(3, '2026-10-05', 'Coffee')
  const fetchMock = fakeApi({
    [LIST]: [200, listBody],
    'POST /api/transactions': [201, JSON.stringify({ success: true, transaction: coffee })],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.create(input)

  expect(calledPaths(fetchMock)).toStrictEqual([LIST, 'POST /api/transactions'])
  expect(transactions.transactions).toStrictEqual([lunch, coffee, gas])
})

test('create on the same date puts the newest first', async () => {
  const dinner = transaction(3, '2026-10-12', 'Dinner')
  fakeApi({
    [LIST]: [200, listBody],
    'POST /api/transactions': [201, JSON.stringify({ success: true, transaction: dinner })],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.create({ ...input, date: '2026-10-12', description: 'Dinner' })

  expect(ids(transactions.transactions)).toStrictEqual([3, 2, 1])
})

test('failed create keeps the list unchanged', async () => {
  fakeApi({
    [LIST]: [200, listBody],
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
    [LIST]: [200, listBody],
    'PUT /api/transactions/1': [200, JSON.stringify({ success: true, transaction: movedGas })],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.update(1, { ...input, date: '2026-10-20', description: 'Gas' })

  expect(calledPaths(fetchMock)).toStrictEqual([LIST, 'PUT /api/transactions/1'])
  expect(transactions.transactions).toStrictEqual([movedGas, lunch])
})

test('failed update keeps the list unchanged', async () => {
  fakeApi({
    [LIST]: [200, listBody],
    'PUT /api/transactions/1': [404, '{"error":"transaction not found"}'],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  const promise = transactions.update(1, input)

  await expect(promise).rejects.toMatchObject({ status: 404, message: 'transaction not found' })
  expect(transactions.transactions).toStrictEqual([lunch, gas])
})

// Outside the loaded months: a saved transaction older than the first loaded month is not shown, its month is not loaded yet

test('create of an older transaction leaves it out of the list', async () => {
  const old = transaction(3, '2026-03-05', 'Coffee')
  fakeApi({
    [LIST]: [200, JSON.stringify({ transactions: [lunch, gas], has_older: false })],
    'POST /api/transactions': [201, JSON.stringify({ success: true, transaction: old })],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.create({ ...input, date: '2026-03-05' })

  expect(transactions.transactions).toStrictEqual([lunch, gas])
  expect(transactions.hasOlder).toBe(true)
})

test('update to an older date takes the transaction out of the list', async () => {
  const movedGas = transaction(1, '2026-03-05', 'Gas')
  fakeApi({
    [LIST]: [200, JSON.stringify({ transactions: [lunch, gas], has_older: false })],
    'PUT /api/transactions/1': [200, JSON.stringify({ success: true, transaction: movedGas })],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.update(1, { ...input, date: '2026-03-05', description: 'Gas' })

  expect(transactions.transactions).toStrictEqual([lunch])
  expect(transactions.hasOlder).toBe(true)
})

// Remove: one request, the transaction leaves the list

test('remove takes the transaction out without loading again', async () => {
  const fetchMock = fakeApi({
    [LIST]: [200, listBody],
    'DELETE /api/transactions/2': [200, '{"success":true}'],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  await transactions.remove(2)

  expect(calledPaths(fetchMock)).toStrictEqual([LIST, 'DELETE /api/transactions/2'])
  expect(transactions.transactions).toStrictEqual([gas])
})

test('failed remove keeps the list unchanged', async () => {
  fakeApi({
    [LIST]: [200, listBody],
    'DELETE /api/transactions/2': [404, '{"error":"transaction not found"}'],
  })
  const transactions = useTransactionsStore()
  await transactions.load()

  const promise = transactions.remove(2)

  await expect(promise).rejects.toMatchObject({ status: 404, message: 'transaction not found' })
  expect(transactions.transactions).toStrictEqual([lunch, gas])
})
