import { request } from './client'
import type {
  CategoriesResponse,
  CategoryResponse,
  CategoryStatsResponse,
  SuccessResponse,
} from './types'

export function listCategories(): Promise<CategoriesResponse> {
  return request<CategoriesResponse>('GET', '/categories')
}

export function createCategory(name: string): Promise<CategoryResponse> {
  return request<CategoryResponse>('POST', '/categories', { name })
}

export function renameCategory(id: number, name: string): Promise<CategoryResponse> {
  return request<CategoryResponse>('PUT', `/categories/${id}`, { name })
}

export function deleteCategory(id: number): Promise<SuccessResponse> {
  return request<SuccessResponse>('DELETE', `/categories/${id}`)
}

export function listCategoryStats(): Promise<CategoryStatsResponse> {
  return request<CategoryStatsResponse>('GET', '/categories/stats')
}
