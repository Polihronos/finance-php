import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import { fakeFetch } from '@/api/__tests__/fakeFetch'
import { ApiError, request, setToken } from '@/api/client'
import { useAuthStore } from '../auth'

const token = '9a59d267832e358d55aef1712779caf7094709418ca846d86fa7fa3ba9ca1b3e'

async function expectClientToSend(headers: Record<string, string>) {
  const fetchMock = fakeFetch(200, '{"categories":[]}')

  await request('GET', '/categories')

  expect(fetchMock).toHaveBeenCalledWith('/api/categories', {
    method: 'GET',
    headers,
    body: undefined,
  })
}

beforeEach(() => {
  localStorage.clear()
  setToken(null)
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.unstubAllGlobals()
})

// Start: picks up the token saved by an earlier visit, so a reload keeps you logged in

test('store starts logged out without a saved token', async () => {
  const auth = useAuthStore()

  expect(auth.isLoggedIn).toBe(false)
  expect(auth.token).toBeNull()
  await expectClientToSend({})
})

test('store starts logged in with a saved token', async () => {
  localStorage.setItem('token', token)

  const auth = useAuthStore()

  expect(auth.isLoggedIn).toBe(true)
  expect(auth.token).toBe(token)
  await expectClientToSend({ Authorization: `Bearer ${token}` })
})

// Login and register: a success saves the token in the store, localStorage and the client

test('login saves the token', async () => {
  fakeFetch(200, `{"success":true,"user_id":1,"token":"${token}"}`)
  const auth = useAuthStore()

  await auth.login('nikola@example.com', 'secret123')

  expect(auth.isLoggedIn).toBe(true)
  expect(auth.token).toBe(token)
  expect(localStorage.getItem('token')).toBe(token)
  await expectClientToSend({ Authorization: `Bearer ${token}` })
})

test('failed login saves nothing', async () => {
  fakeFetch(401, '{"error":"invalid email or password"}')
  const auth = useAuthStore()

  const promise = auth.login('nikola@example.com', 'wrongpass1')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 401, message: 'invalid email or password' })
  expect(auth.isLoggedIn).toBe(false)
  expect(localStorage.getItem('token')).toBeNull()
  await expectClientToSend({})
})

test('register saves the token', async () => {
  fakeFetch(201, `{"success":true,"user_id":1,"token":"${token}"}`)
  const auth = useAuthStore()

  await auth.register('Nikola', 'nikola@example.com', 'secret123')

  expect(auth.isLoggedIn).toBe(true)
  expect(auth.token).toBe(token)
  expect(localStorage.getItem('token')).toBe(token)
  await expectClientToSend({ Authorization: `Bearer ${token}` })
})

test('failed register saves nothing', async () => {
  fakeFetch(409, '{"error":"email already registered"}')
  const auth = useAuthStore()

  const promise = auth.register('Nikola', 'nikola@example.com', 'secret123')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 409, message: 'email already registered' })
  expect(auth.isLoggedIn).toBe(false)
  expect(localStorage.getItem('token')).toBeNull()
  await expectClientToSend({})
})

// Logout: clears the token everywhere, even when the API call fails

test('logout clears the token', async () => {
  localStorage.setItem('token', token)
  const auth = useAuthStore()
  fakeFetch(200, '{"success":true}')

  await auth.logout()

  expect(auth.isLoggedIn).toBe(false)
  expect(auth.token).toBeNull()
  expect(localStorage.getItem('token')).toBeNull()
  await expectClientToSend({})
})

test('logout clears the token when the api says not logged in', async () => {
  localStorage.setItem('token', token)
  const auth = useAuthStore()
  fakeFetch(401, '{"error":"not logged in"}')

  const promise = auth.logout()

  await expect(promise).rejects.toMatchObject({ status: 401, message: 'not logged in' })
  expect(auth.isLoggedIn).toBe(false)
  expect(localStorage.getItem('token')).toBeNull()
  await expectClientToSend({})
})

test('logout clears the token when the server is down', async () => {
  localStorage.setItem('token', token)
  const auth = useAuthStore()
  fakeFetch(500, 'Internal Server Error')

  const promise = auth.logout()

  await expect(promise).rejects.toMatchObject({ status: 500, message: 'request failed (500)' })
  expect(auth.isLoggedIn).toBe(false)
  expect(localStorage.getItem('token')).toBeNull()
  await expectClientToSend({})
})

// Forget: clears the token everywhere without asking the API, used after a 401

test('forget clears the token without calling the api', async () => {
  localStorage.setItem('token', token)
  const auth = useAuthStore()
  const fetchMock = fakeFetch(200, '{"success":true}')

  auth.forget()

  expect(fetchMock).not.toHaveBeenCalled()
  expect(auth.isLoggedIn).toBe(false)
  expect(auth.token).toBeNull()
  expect(localStorage.getItem('token')).toBeNull()
  await expectClientToSend({})
})
