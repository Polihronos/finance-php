import { ref } from 'vue'
import { defineStore } from 'pinia'

import type { Category, CategoryStats } from '@/api/types'
import * as categoriesApi from '@/api/categories'
import { usePeriodStore } from '@/stores/period'

export const useCategoriesStore = defineStore('categories', () => {
  const categories = ref<Category[]>([])
  const stats = ref<CategoryStats[]>([])
  const periodStore = usePeriodStore()

  async function load(): Promise<void> {
    const [categoriesAnswer, statsAnswer] = await Promise.all([
      categoriesApi.listCategories(),
      categoriesApi.listCategoryStats(periodStore.statsPeriod),
    ])
    categories.value = categoriesAnswer.categories
    stats.value = statsAnswer.stats
  }

  async function loadStats(): Promise<void> {
    const answer = await categoriesApi.listCategoryStats(periodStore.statsPeriod)
    stats.value = answer.stats
  }

  async function create(name: string): Promise<void> {
    await categoriesApi.createCategory(name)
    await load()
  }

  async function rename(id: number, name: string): Promise<void> {
    await categoriesApi.renameCategory(id, name)
    await load()
  }

  async function remove(id: number): Promise<void> {
    await categoriesApi.deleteCategory(id)
    await load()
  }

  function statsFor(categoryId: number): CategoryStats | undefined {
    return stats.value.find((row) => row.category_id === categoryId)
  }

  return { categories, stats, load, loadStats, create, rename, remove, statsFor }
})
