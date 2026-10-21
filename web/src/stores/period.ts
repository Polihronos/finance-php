import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import type { StatsPeriod } from '@/api/types'

export type Period = 'month' | 'year'

const STORAGE_KEY = 'period'

function currentMonth(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

export const usePeriodStore = defineStore('period', () => {
  const period = ref<Period>(localStorage.getItem(STORAGE_KEY) === 'year' ? 'year' : 'month')
  const month = ref(currentMonth())

  const statsPeriod = computed<StatsPeriod>(() =>
    period.value === 'month' ? { month: month.value } : { year: month.value.slice(0, 4) },
  )

  const label = computed(() => {
    if (period.value === 'year') {
      return month.value.slice(0, 4)
    }
    return new Date(`${month.value}-01T00:00:00Z`).toLocaleString('en-GB', {
      month: 'long',
      year: 'numeric',
      timeZone: 'UTC',
    })
  })

  const atLatest = computed(() =>
    period.value === 'month'
      ? month.value >= currentMonth()
      : month.value.slice(0, 4) >= currentMonth().slice(0, 4),
  )

  function shift(step: number): void {
    const year = Number(month.value.slice(0, 4))
    const monthIndex = Number(month.value.slice(5, 7)) - 1
    const date =
      period.value === 'month'
        ? new Date(Date.UTC(year, monthIndex + step, 1))
        : new Date(Date.UTC(year + step, monthIndex, 1))
    const next = date.toISOString().slice(0, 7)
    month.value = next > currentMonth() ? currentMonth() : next
  }

  function toggle(): void {
    period.value = period.value === 'month' ? 'year' : 'month'
    localStorage.setItem(STORAGE_KEY, period.value)
  }

  return { period, month, statsPeriod, label, atLatest, shift, toggle }
})
