import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'

import { usePeriodStore } from '../period'

beforeEach(() => {
  localStorage.clear()
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.useRealTimers()
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

// Month: starts on the current month, statsPeriod asks for that month or for its year

test('month starts on the current month', () => {
  vi.useFakeTimers()
  vi.setSystemTime(new Date(2026, 9, 19))

  const period = usePeriodStore()

  expect(period.month).toBe('2026-10')
})

test('statsPeriod asks for the chosen month', () => {
  const period = usePeriodStore()
  period.month = '2026-07'

  expect(period.statsPeriod).toStrictEqual({ month: '2026-07' })
})

test('statsPeriod asks for the year of the chosen month when yearly is on', () => {
  const period = usePeriodStore()
  period.month = '2026-07'

  period.toggle()

  expect(period.statsPeriod).toStrictEqual({ year: '2026' })
})
