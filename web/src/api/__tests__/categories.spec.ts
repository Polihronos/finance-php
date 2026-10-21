import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import {
  createCategory,
  deleteCategory,
  listCategories,
  listCategoryStats,
  updateCategory,
} from '../categories'
import { ApiError, setToken } from '../client'
import { fakeFetch } from './fakeFetch'

const token = '9a59d267832e358d55aef1712779caf7094709418ca846d86fa7fa3ba9ca1b3e'

beforeEach(() => {
  setToken(token)
})

afterEach(() => {
  setToken(null)
  vi.unstubAllGlobals()
})

// List: gets the categories without a body, the token goes in the header

test('listCategories gets the categories', async () => {
  const fetchMock = fakeFetch(
    200,
    '{"categories":[{"id":2,"name":"Bills","icon":"zap","color":"yellow"},' +
      '{"id":1,"name":"Food","icon":"utensils","color":"orange"}]}',
  )

  const answer = await listCategories()

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'GET',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
  expect(answer).toStrictEqual({
    categories: [
      { id: 2, name: 'Bills', icon: 'zap', color: 'yellow' },
      { id: 1, name: 'Food', icon: 'utensils', color: 'orange' },
    ],
  })
})

test('listCategories passes on the api error', async () => {
  fakeFetch(401, '{"error":"not logged in"}')

  const promise = listCategories()

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 401, message: 'not logged in' })
})

// Create: posts the name, icon and color, returns the new category

test('createCategory posts the name, icon and color', async () => {
  const fetchMock = fakeFetch(
    201,
    '{"success":true,"category_id":1,"name":"Food","icon":"utensils","color":"orange"}',
  )

  const answer = await createCategory({ name: 'Food', icon: 'utensils', color: 'orange' })

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: '{"name":"Food","icon":"utensils","color":"orange"}',
  })
  expect(answer).toStrictEqual({
    success: true,
    category_id: 1,
    name: 'Food',
    icon: 'utensils',
    color: 'orange',
  })
})

test('createCategory passes on the api error', async () => {
  fakeFetch(409, '{"error":"category already exists"}')

  const promise = createCategory({ name: 'Food' })

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 409, message: 'category already exists' })
})

// Update: puts the name, and the icon and color when given, to the category's id

test('updateCategory puts the fields to the id', async () => {
  const fetchMock = fakeFetch(
    200,
    '{"success":true,"category_id":3,"name":"Groceries","icon":"shopping-cart","color":"green"}',
  )

  const answer = await updateCategory(3, {
    name: 'Groceries',
    icon: 'shopping-cart',
    color: 'green',
  })

  expect(fetchMock).toHaveBeenCalledWith('/api/categories/3', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: '{"name":"Groceries","icon":"shopping-cart","color":"green"}',
  })
  expect(answer).toStrictEqual({
    success: true,
    category_id: 3,
    name: 'Groceries',
    icon: 'shopping-cart',
    color: 'green',
  })
})

test('updateCategory sends only the name when that is all that changes', async () => {
  const fetchMock = fakeFetch(
    200,
    '{"success":true,"category_id":3,"name":"Groceries","icon":"tag","color":"blue"}',
  )

  await updateCategory(3, { name: 'Groceries' })

  expect(fetchMock).toHaveBeenCalledWith(
    '/api/categories/3',
    expect.objectContaining({ body: '{"name":"Groceries"}' }),
  )
})

test('updateCategory passes on the api error', async () => {
  fakeFetch(404, '{"error":"category not found"}')

  const promise = updateCategory(3, { name: 'Groceries' })

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 404, message: 'category not found' })
})

// Delete: deletes the category's id without a body

test('deleteCategory deletes the id', async () => {
  const fetchMock = fakeFetch(200, '{"success":true}')

  const answer = await deleteCategory(3)

  expect(fetchMock).toHaveBeenCalledWith('/api/categories/3', {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
  expect(answer).toStrictEqual({ success: true })
})

test('deleteCategory passes on the api error', async () => {
  fakeFetch(409, '{"error":"category is used by transactions"}')

  const promise = deleteCategory(3)

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({
    status: 409,
    message: 'category is used by transactions',
  })
})

// Stats: gets the count and total of every category for one month or one year

test('listCategoryStats gets the stats of a month', async () => {
  const fetchMock = fakeFetch(
    200,
    '{"stats":[{"category_id":1,"transaction_count":2,"total":"30.49"}]}',
  )

  const answer = await listCategoryStats({ month: '2026-10' })

  expect(fetchMock).toHaveBeenCalledWith('/api/categories/stats?month=2026-10', {
    method: 'GET',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
  expect(answer).toStrictEqual({
    stats: [{ category_id: 1, transaction_count: 2, total: '30.49' }],
  })
})

test('listCategoryStats asks for a year', async () => {
  const fetchMock = fakeFetch(200, '{"stats":[]}')

  await listCategoryStats({ year: '2026' })

  expect(fetchMock).toHaveBeenCalledWith('/api/categories/stats?year=2026', expect.anything())
})

test('listCategoryStats passes on the api error', async () => {
  fakeFetch(401, '{"error":"not logged in"}')

  const promise = listCategoryStats({ month: '2026-10' })

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 401, message: 'not logged in' })
})
