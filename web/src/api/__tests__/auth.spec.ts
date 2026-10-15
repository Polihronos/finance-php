import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import { login, logout, register } from '../auth'
import { ApiError, setToken } from '../client'
import { fakeFetch } from './fakeFetch'

const token = '9a59d267832e358d55aef1712779caf7094709418ca846d86fa7fa3ba9ca1b3e'

beforeEach(() => {
  setToken(null)
})

afterEach(() => {
  vi.unstubAllGlobals()
})

// Register: posts name, email and password, returns the new user's token

test('register posts name email and password', async () => {
  const fetchMock = fakeFetch(201, `{"success":true,"user_id":1,"token":"${token}"}`)

  await register('Nikola', 'nikola@example.com', 'secret123')

  expect(fetchMock).toHaveBeenCalledWith('/api/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{"name":"Nikola","email":"nikola@example.com","password":"secret123"}',
  })
})

test('register returns the token', async () => {
  fakeFetch(201, `{"success":true,"user_id":1,"token":"${token}"}`)

  const answer = await register('Nikola', 'nikola@example.com', 'secret123')

  expect(answer).toStrictEqual({ success: true, user_id: 1, token })
})

test('register passes on the api error', async () => {
  fakeFetch(409, '{"error":"email already registered"}')

  const promise = register('Nikola', 'nikola@example.com', 'secret123')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 409, message: 'email already registered' })
})

// Login: posts email and password, returns a new token

test('login posts email and password', async () => {
  const fetchMock = fakeFetch(200, `{"success":true,"user_id":1,"token":"${token}"}`)

  await login('nikola@example.com', 'secret123')

  expect(fetchMock).toHaveBeenCalledWith('/api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{"email":"nikola@example.com","password":"secret123"}',
  })
})

test('login returns the token', async () => {
  fakeFetch(200, `{"success":true,"user_id":1,"token":"${token}"}`)

  const answer = await login('nikola@example.com', 'secret123')

  expect(answer).toStrictEqual({ success: true, user_id: 1, token })
})

test('login passes on the api error', async () => {
  fakeFetch(401, '{"error":"invalid email or password"}')

  const promise = login('nikola@example.com', 'wrongpass1')

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 401, message: 'invalid email or password' })
})

// Logout: posts without a body, the token goes in the header

test('logout posts the token without a body', async () => {
  const fetchMock = fakeFetch(200, '{"success":true}')
  setToken(token)

  await logout()

  expect(fetchMock).toHaveBeenCalledWith('/api/logout', {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
    body: undefined,
  })
})

test('logout returns success', async () => {
  fakeFetch(200, '{"success":true}')
  setToken(token)

  const answer = await logout()

  expect(answer).toStrictEqual({ success: true })
})

test('logout passes on the api error', async () => {
  fakeFetch(401, '{"error":"not logged in"}')

  const promise = logout()

  await expect(promise).rejects.toBeInstanceOf(ApiError)
  await expect(promise).rejects.toMatchObject({ status: 401, message: 'not logged in' })
})
