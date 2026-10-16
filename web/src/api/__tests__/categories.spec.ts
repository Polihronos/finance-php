import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import {
  createCategory,
  deleteCategory,
  listCategories,
  listCategoryStats,
  renameCategory,
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
    '{"categories":[{"id":2,"name":"Bills"},{"id":1,"name":"Food"}]}',
  )

  const answer = await listCategories()

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'GET',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
  expect(answer).toStrictEqual({
    categories: [
      { id: 2, name: 'Bills' },
      { id: 1, name: 'Food' },
    ],
  })
})

test('listCategories passes on the api error', async () => {
  fakeFetch(401, '{"error":"not logged in"}')

  const promise = listCategories()

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 401, message: 'not logged in' })
})

// Create: posts the name, returns the new category

test('createCategory posts the name', async () => {
  const fetchMock = fakeFetch(201, '{"success":true,"category_id":1,"name":"Food"}')

  const answer = await createCategory('Food')

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: '{"name":"Food"}',
  })
  expect(answer).toStrictEqual({ success: true, category_id: 1, name: 'Food' })
})

test('createCategory passes on the api error', async () => {
  fakeFetch(409, '{"error":"category already exists"}')

  const promise = createCategory('Food')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 409, message: 'category already exists' })
})

// Rename: puts the new name to the category's id

test('renameCategory puts the name to the id', async () => {
  const fetchMock = fakeFetch(200, '{"success":true,"category_id":3,"name":"Groceries"}')

  const answer = await renameCategory(3, 'Groceries')

  expect(fetchMock).toHaveBeenCalledWith('/api/categories/3', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: '{"name":"Groceries"}',
  })
  expect(answer).toStrictEqual({ success: true, category_id: 3, name: 'Groceries' })
})

test('renameCategory passes on the api error', async () => {
  fakeFetch(404, '{"error":"category not found"}')

  const promise = renameCategory(3, 'Groceries')

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

// Stats: gets the count and monthly average of every category

test('listCategoryStats gets the stats', async () => {
  const fetchMock = fakeFetch(
    200,
    '{"stats":[{"category_id":1,"transaction_count":12,"monthly_average":"30.00"}]}',
  )

  const answer = await listCategoryStats()

  expect(fetchMock).toHaveBeenCalledWith('/api/categories/stats', {
    method: 'GET',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
  expect(answer).toStrictEqual({
    stats: [{ category_id: 1, transaction_count: 12, monthly_average: '30.00' }],
  })
})

test('listCategoryStats passes on the api error', async () => {
  fakeFetch(401, '{"error":"not logged in"}')

  const promise = listCategoryStats()

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 401, message: 'not logged in' })
})
