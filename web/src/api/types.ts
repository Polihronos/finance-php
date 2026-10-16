export type AuthResponse = {
  success: boolean
  user_id: number
  token: string
}

export type SuccessResponse = {
  success: boolean
}

export type Category = {
  id: number
  name: string
}

export type CategoriesResponse = {
  categories: Category[]
}

export type CategoryResponse = {
  success: boolean
  category_id: number
  name: string
}

export type CategoryStats = {
  category_id: number
  transaction_count: number
  monthly_average: string
}

export type CategoryStatsResponse = {
  stats: CategoryStats[]
}
