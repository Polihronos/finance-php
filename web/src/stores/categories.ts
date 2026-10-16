import { ref } from 'vue'
import { defineStore } from 'pinia'

import type { Category, CategoryStats } from '@/api/types'
import * as categoriesApi from '@/api/categories'

export const useCategoriesStore = defineStore('categories', () => {
  const categories = ref<Category[]>([])
  const stats = ref<CategoryStats[]>([])

  async function load(): Promise<void> {
    const [categoriesAnswer, statsAnswer] = await Promise.all([
      categoriesApi.listCategories(),
      categoriesApi.listCategoryStats(),
    ])
    categories.value = categoriesAnswer.categories
    stats.value = statsAnswer.stats
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

  return { categories, stats, load, create, rename, remove, statsFor }
})
