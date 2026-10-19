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

  function toggle(): void {
    period.value = period.value === 'month' ? 'year' : 'month'
    localStorage.setItem(STORAGE_KEY, period.value)
  }

  return { period, month, statsPeriod, toggle }
})
