import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import { ApiError, setToken } from '../client'
import {
  createTransaction,
  deleteTransaction,
  listTransactions,
  suggestTransactions,
  updateTransaction,
} from '../transactions'
import type { TransactionInput } from '../types'
import { fakeFetch } from './fakeFetch'

const token = '9a59d267832e358d55aef1712779caf7094709418ca846d86fa7fa3ba9ca1b3e'

const input: TransactionInput = {
  category_id: 1,
  amount: 12.5,
  date: '2026-10-12',
  payment_method: 'card',
  description: 'Lunch',
  place: null,
}

const saved = {
  id: 3,
  category_id: 1,
  amount: '12.50',
  currency: 'EUR',
  date: '2026-10-12',
  payment_method: 'card',
  description: 'Lunch',
  place: null,
}

beforeEach(() => {
  setToken(token)
})

afterEach(() => {
  setToken(null)
  vi.unstubAllGlobals()
})

// List: gets the transactions without a body, the token goes in the header

test('listTransactions gets the transactions', async () => {
  const fetchMock = fakeFetch(200, JSON.stringify({ transactions: [saved] }))

  const answer = await listTransactions()

  expect(fetchMock).toHaveBeenCalledWith('/api/transactions', {
    method: 'GET',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
  expect(answer).toStrictEqual({ transactions: [saved] })
})

test('listTransactions passes on the api error', async () => {
  fakeFetch(401, '{"error":"not logged in"}')

  const promise = listTransactions()

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 401, message: 'not logged in' })
})

// Create: posts every field, returns the saved transaction with amount as text

test('createTransaction posts the fields', async () => {
  const fetchMock = fakeFetch(201, JSON.stringify({ success: true, transaction: saved }))

  const answer = await createTransaction(input)

  expect(fetchMock).toHaveBeenCalledWith('/api/transactions', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: '{"category_id":1,"amount":12.5,"date":"2026-10-12","payment_method":"card","description":"Lunch","place":null}',
  })
  expect(answer).toStrictEqual({ success: true, transaction: saved })
})

test('createTransaction passes on the api error', async () => {
  fakeFetch(400, '{"error":"category not found"}')

  const promise = createTransaction(input)

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 400, message: 'category not found' })
})

// Update: puts every field to the transaction's id

test('updateTransaction puts the fields to the id', async () => {
  const fetchMock = fakeFetch(200, JSON.stringify({ success: true, transaction: saved }))

  const answer = await updateTransaction(3, input)

  expect(fetchMock).toHaveBeenCalledWith('/api/transactions/3', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: '{"category_id":1,"amount":12.5,"date":"2026-10-12","payment_method":"card","description":"Lunch","place":null}',
  })
  expect(answer).toStrictEqual({ success: true, transaction: saved })
})

test('updateTransaction passes on the api error', async () => {
  fakeFetch(404, '{"error":"transaction not found"}')

  const promise = updateTransaction(3, input)

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 404, message: 'transaction not found' })
})

// Delete: deletes the transaction's id without a body

test('deleteTransaction deletes the id', async () => {
  const fetchMock = fakeFetch(200, '{"success":true}')

  const answer = await deleteTransaction(3)

  expect(fetchMock).toHaveBeenCalledWith('/api/transactions/3', {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
  expect(answer).toStrictEqual({ success: true })
})

test('deleteTransaction passes on the api error', async () => {
  fakeFetch(404, '{"error":"transaction not found"}')

  const promise = deleteTransaction(3)

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 404, message: 'transaction not found' })
})

// Suggestions: the typed text goes in the address as q, encoded so & and % stay text

test('suggestTransactions gets the suggestions', async () => {
  const fetchMock = fakeFetch(
    200,
    '{"suggestions":[{"description":"Gas","category_id":2,"place":"OMV"}]}',
  )

  const answer = await suggestTransactions('ga')

  expect(fetchMock).toHaveBeenCalledWith('/api/transactions/suggestions?q=ga', {
    method: 'GET',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
  expect(answer).toStrictEqual({
    suggestions: [{ description: 'Gas', category_id: 2, place: 'OMV' }],
  })
})

test('suggestTransactions encodes the text', async () => {
  const fetchMock = fakeFetch(200, '{"suggestions":[]}')

  await suggestTransactions('M&M 100%')

  expect(fetchMock).toHaveBeenCalledWith(
    '/api/transactions/suggestions?q=M%26M%20100%25',
    expect.anything(),
  )
})

test('suggestTransactions passes on the api error', async () => {
  fakeFetch(400, '{"error":"q is required"}')

  const promise = suggestTransactions('')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 400, message: 'q is required' })
})
