import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, expect, test } from 'vitest'

import { usePeriodStore } from '../period'

beforeEach(() => {
  localStorage.clear()
  setActivePinia(createPinia())
})

// Start: picks up the choice saved by an earlier visit, anything else means month

test('store starts on month without a saved choice', () => {
  const period = usePeriodStore()

  expect(period.period).toBe('month')
})

test('store starts on year when year is saved', () => {
  localStorage.setItem('period', 'year')

  const period = usePeriodStore()

  expect(period.period).toBe('year')
})

test('store starts on month when the saved value is broken', () => {
  localStorage.setItem('period', 'week')

  const period = usePeriodStore()

  expect(period.period).toBe('month')
})

// Toggle: flips to the other period and saves it right away

test('toggle switches to year and saves it', () => {
  const period = usePeriodStore()

  period.toggle()

  expect(period.period).toBe('year')
  expect(localStorage.getItem('period')).toBe('year')
})

test('toggle twice switches back to month and saves it', () => {
  const period = usePeriodStore()

  period.toggle()
  period.toggle()

  expect(period.period).toBe('month')
  expect(localStorage.getItem('period')).toBe('month')
})
