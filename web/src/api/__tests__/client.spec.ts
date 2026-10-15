import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import { ApiError, request, setToken, setUnauthorizedHandler } from '../client'
import { fakeFetch } from './fakeFetch'

beforeEach(() => {
  setToken(null)
  setUnauthorizedHandler(() => {})
})

afterEach(() => {
  vi.unstubAllGlobals()
})

// Sending: the path gets /api, a body is sent as JSON, the token goes into the Authorization header

test('request sends a get to the api path without body or headers', async () => {
  const fetchMock = fakeFetch(200, '{"categories":[]}')

  await request('GET', '/categories')

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'GET',
    headers: {},
    body: undefined,
  })
})

test('request sends a body as json', async () => {
  const fetchMock = fakeFetch(201, '{"success":true,"category_id":1,"name":"Food"}')

  await request('POST', '/categories', { name: 'Food' })

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{"name":"Food"}',
  })
})

test('request sends the token after set token', async () => {
  const fetchMock = fakeFetch(200, '{"categories":[]}')
  setToken('abc123')

  await request('GET', '/categories')

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'GET',
    headers: { Authorization: 'Bearer abc123' },
    body: undefined,
  })
})

test('request stops sending the token after set token null', async () => {
  const fetchMock = fakeFetch(200, '{"categories":[]}')
  setToken('abc123')
  setToken(null)

  await request('GET', '/categories')

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'GET',
    headers: {},
    body: undefined,
  })
})

// Answers: a success returns the JSON, an error status throws ApiError with the API's message

test('request returns the json of a success', async () => {
  fakeFetch(200, '{"categories":[{"id":1,"name":"Food"}]}')

  const answer = await request('GET', '/categories')

  expect(answer).toStrictEqual({ categories: [{ id: 1, name: 'Food' }] })
})

test('request throws api error with the api message', async () => {
  fakeFetch(404, '{"error":"category not found"}')

  const promise = request('DELETE', '/categories/99')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 404, message: 'category not found' })
})

test('request throws api error when the answer is not json', async () => {
  fakeFetch(500, 'Internal Server Error')

  const promise = request('GET', '/categories')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 500, message: 'request failed (500)' })
})

test('request throws api error when the error answer has no error text', async () => {
  fakeFetch(400, '{"success":false}')

  const promise = request('POST', '/categories', { name: 'Food' })

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 400, message: 'request failed (400)' })
})

// Unauthorized: a 401 with a token calls the handler, a 401 without a token (wrong password) does not

test('request calls the unauthorized handler on 401 with a token', async () => {
  fakeFetch(401, '{"error":"not logged in"}')
  const handler = vi.fn<() => void>()
  setUnauthorizedHandler(handler)
  setToken('abc123')

  const promise = request('GET', '/categories')

  await expect(promise).rejects.toMatchObject({ status: 401, message: 'not logged in' })
  expect(handler).toHaveBeenCalledTimes(1)
})

test('request does not call the unauthorized handler on 401 without a token', async () => {
  fakeFetch(401, '{"error":"invalid email or password"}')
  const handler = vi.fn<() => void>()
  setUnauthorizedHandler(handler)

  const promise = request('POST', '/login', { email: 'nikola@example.com', password: 'wrongpass1' })

  await expect(promise).rejects.toMatchObject({ status: 401, message: 'invalid email or password' })
  expect(handler).not.toHaveBeenCalled()
})

test('request does not call the unauthorized handler on other errors', async () => {
  fakeFetch(404, '{"error":"category not found"}')
  const handler = vi.fn<() => void>()
  setUnauthorizedHandler(handler)
  setToken('abc123')

  const promise = request('DELETE', '/categories/99')

  await expect(promise).rejects.toMatchObject({ status: 404, message: 'category not found' })
  expect(handler).not.toHaveBeenCalled()
})
