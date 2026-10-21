import { request } from './client'
import type {
  CategoriesResponse,
  CategoryInput,
  CategoryResponse,
  CategoryStatsResponse,
  StatsPeriod,
  SuccessResponse,
} from './types'

export function listCategories(): Promise<CategoriesResponse> {
  return request<CategoriesResponse>('GET', '/categories')
}

export function createCategory(input: CategoryInput): Promise<CategoryResponse> {
  return request<CategoryResponse>('POST', '/categories', input)
}

export function updateCategory(id: number, input: CategoryInput): Promise<CategoryResponse> {
  return request<CategoryResponse>('PUT', `/categories/${id}`, input)
}

export function deleteCategory(id: number): Promise<SuccessResponse> {
  return request<SuccessResponse>('DELETE', `/categories/${id}`)
}

export function listCategoryStats(period: StatsPeriod): Promise<CategoryStatsResponse> {
  return request<CategoryStatsResponse>('GET', `/categories/stats?${new URLSearchParams(period)}`)
}
