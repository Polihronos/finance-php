export type AuthResponse = {
  success: boolean
  user_id: number
  token: string
}

export type SuccessResponse = {
  success: boolean
}

export type CategoryIcon =
  | 'shopping-cart'
  | 'utensils'
  | 'coffee'
  | 'car'
  | 'fuel'
  | 'bus'
  | 'plane'
  | 'house'
  | 'zap'
  | 'droplet'
  | 'wifi'
  | 'smartphone'
  | 'heart-pulse'
  | 'pill'
  | 'shirt'
  | 'gift'
  | 'film'
  | 'gamepad-2'
  | 'dumbbell'
  | 'graduation-cap'
  | 'baby'
  | 'paw-print'
  | 'briefcase'
  | 'piggy-bank'
  | 'tag'

export type CategoryColor = 'red' | 'orange' | 'yellow' | 'green' | 'blue' | 'purple'

export type Category = {
  id: number
  name: string
  icon: CategoryIcon
  color: CategoryColor
}

export type CategoryInput = {
  name: string
  icon?: CategoryIcon
  color?: CategoryColor
}

export type CategoriesResponse = {
  categories: Category[]
}

export type CategoryResponse = {
  success: boolean
  category_id: number
  name: string
  icon: CategoryIcon
  color: CategoryColor
}

export type CategoryStats = {
  category_id: number
  transaction_count: number
  total: string
}

export type StatsPeriod = { month: string } | { year: string }

export type CategoryStatsResponse = {
  stats: CategoryStats[]
}

export type PaymentMethod = 'cash' | 'card'

export type Transaction = {
  id: number
  category_id: number
  amount: string
  currency: string
  date: string
  payment_method: PaymentMethod
  description: string | null
  place: string | null
}

export type TransactionInput = {
  category_id: number
  amount: number
  date: string
  payment_method: PaymentMethod
  description: string | null
  place: string | null
}

export type DateRange = {
  from: string
  to: string
}

export type TransactionsResponse = {
  transactions: Transaction[]
  has_older: boolean
}

export type TransactionResponse = {
  success: boolean
  transaction: Transaction
}

export type Suggestion = {
  description: string
  category_id: number
  place: string | null
}

export type SuggestionsResponse = {
  suggestions: Suggestion[]
}
