import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, expect, test } from 'vitest'

import router from '../index'

beforeEach(() => {
  localStorage.clear()
  setActivePinia(createPinia())
})

// Unknown addresses: go to Transactions, and the guard still sends logged-out users to log in

test('an unknown address goes to Transactions when logged in', async () => {
  localStorage.setItem('token', 'saved-token')

  await router.push('/something-that-does-not-exist')

  expect(router.currentRoute.value.name).toBe('transactions')
})

test('an unknown address goes to log in when logged out', async () => {
  await router.push('/register')

  await router.push('/something-that-does-not-exist')

  expect(router.currentRoute.value.name).toBe('login')
})
