import { expect, test } from 'vitest'

import { formatEur } from '../money'

// formatEur: comma for cents, no ,00 on whole euros

test('formatEur leaves out ,00 on whole euros', () => {
  expect(formatEur('100.00')).toBe('100 EUR')
})

test('formatEur keeps cents', () => {
  expect(formatEur('100.50')).toBe('100,50 EUR')
  expect(formatEur('18.49')).toBe('18,49 EUR')
})

test('formatEur shows zero as 0', () => {
  expect(formatEur('0.00')).toBe('0 EUR')
})
