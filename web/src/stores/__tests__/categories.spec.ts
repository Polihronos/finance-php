import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import { ApiError } from '@/api/client'
import { useCategoriesStore } from '../categories'

const categoriesBody = '{"categories":[{"id":2,"name":"Bills"},{"id":1,"name":"Food"}]}'
const statsBody =
  '{"stats":[{"category_id":1,"transaction_count":12,"monthly_average":"30.00"},' +
  '{"category_id":2,"transaction_count":0,"monthly_average":"0.00"}]}'

// Answers each "METHOD /path" with its own status and body, so load() can ask for categories and stats at once
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

const loadAnswers: Record<string, [number, string]> = {
  'GET /api/categories': [200, categoriesBody],
  'GET /api/categories/stats': [200, statsBody],
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.unstubAllGlobals()
})

// Load: fetches the categories and their stats together

test('load fills categories and stats', async () => {
  fakeApi(loadAnswers)
  const categories = useCategoriesStore()

  await categories.load()

  expect(categories.categories).toStrictEqual([
    { id: 2, name: 'Bills' },
    { id: 1, name: 'Food' },
  ])
  expect(categories.stats).toStrictEqual([
    { category_id: 1, transaction_count: 12, monthly_average: '30.00' },
    { category_id: 2, transaction_count: 0, monthly_average: '0.00' },
  ])
})

test('failed stats keep both lists unchanged', async () => {
  fakeApi({
    'GET /api/categories': [200, categoriesBody],
    'GET /api/categories/stats': [500, 'Internal Server Error'],
  })
  const categories = useCategoriesStore()

  const promise = categories.load()

  await expect(promise).rejects.toMatchObject({ status: 500, message: 'request failed (500)' })
  expect(categories.categories).toStrictEqual([])
  expect(categories.stats).toStrictEqual([])
})

// Create, rename and remove: a success loads the lists again, a failure loads nothing

test('create posts then loads again', async () => {
  const fetchMock = fakeApi({
    ...loadAnswers,
    'POST /api/categories': [201, '{"success":true,"category_id":3,"name":"Rent"}'],
  })
  const categories = useCategoriesStore()

  await categories.create('Rent')

  expect(calledPaths(fetchMock)).toStrictEqual([
    'POST /api/categories',
    'GET /api/categories',
    'GET /api/categories/stats',
  ])
  expect(categories.categories).toHaveLength(2)
})

test('failed create does not load again', async () => {
  const fetchMock = fakeApi({
    ...loadAnswers,
    'POST /api/categories': [409, '{"error":"category already exists"}'],
  })
  const categories = useCategoriesStore()

  const promise = categories.create('Food')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 409, message: 'category already exists' })
  expect(calledPaths(fetchMock)).toStrictEqual(['POST /api/categories'])
})

test('rename puts then loads again', async () => {
  const fetchMock = fakeApi({
    ...loadAnswers,
    'PUT /api/categories/1': [200, '{"success":true,"category_id":1,"name":"Groceries"}'],
  })
  const categories = useCategoriesStore()

  await categories.rename(1, 'Groceries')

  expect(calledPaths(fetchMock)).toStrictEqual([
    'PUT /api/categories/1',
    'GET /api/categories',
    'GET /api/categories/stats',
  ])
})

test('remove deletes then loads again', async () => {
  const fetchMock = fakeApi({
    ...loadAnswers,
    'DELETE /api/categories/2': [200, '{"success":true}'],
  })
  const categories = useCategoriesStore()

  await categories.remove(2)

  expect(calledPaths(fetchMock)).toStrictEqual([
    'DELETE /api/categories/2',
    'GET /api/categories',
    'GET /api/categories/stats',
  ])
})

test('failed remove does not load again', async () => {
  const fetchMock = fakeApi({
    ...loadAnswers,
    'DELETE /api/categories/1': [409, '{"error":"category is used by transactions"}'],
  })
  const categories = useCategoriesStore()

  const promise = categories.remove(1)

  await expect(promise).rejects.toMatchObject({
    status: 409,
    message: 'category is used by transactions',
  })
  expect(calledPaths(fetchMock)).toStrictEqual(['DELETE /api/categories/1'])
})

// statsFor: finds one category's stats row for its card

test('statsFor finds the row of a category', async () => {
  fakeApi(loadAnswers)
  const categories = useCategoriesStore()
  await categories.load()

  expect(categories.statsFor(1)).toStrictEqual({
    category_id: 1,
    transaction_count: 12,
    monthly_average: '30.00',
  })
})

test('statsFor returns undefined for an unknown category', async () => {
  fakeApi(loadAnswers)
  const categories = useCategoriesStore()
  await categories.load()

  expect(categories.statsFor(99)).toBeUndefined()
})
