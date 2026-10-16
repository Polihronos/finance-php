import { ref } from 'vue'
import { defineStore } from 'pinia'

export type Period = 'month' | 'year'

const STORAGE_KEY = 'period'

export const usePeriodStore = defineStore('period', () => {
  const period = ref<Period>(localStorage.getItem(STORAGE_KEY) === 'year' ? 'year' : 'month')

  function toggle(): void {
    period.value = period.value === 'month' ? 'year' : 'month'
    localStorage.setItem(STORAGE_KEY, period.value)
  }

  return { period, toggle }
})
