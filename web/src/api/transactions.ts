import { request } from './client'
import type {
  DateRange,
  SuccessResponse,
  SuggestionsResponse,
  TransactionInput,
  TransactionResponse,
  TransactionsResponse,
} from './types'

export function listTransactions(range: DateRange): Promise<TransactionsResponse> {
  return request<TransactionsResponse>('GET', `/transactions?${new URLSearchParams(range)}`)
}

export function createTransaction(input: TransactionInput): Promise<TransactionResponse> {
  return request<TransactionResponse>('POST', '/transactions', input)
}

export function updateTransaction(
  id: number,
  input: TransactionInput,
): Promise<TransactionResponse> {
  return request<TransactionResponse>('PUT', `/transactions/${id}`, input)
}

export function deleteTransaction(id: number): Promise<SuccessResponse> {
  return request<SuccessResponse>('DELETE', `/transactions/${id}`)
}

export function suggestTransactions(query: string): Promise<SuggestionsResponse> {
  return request<SuggestionsResponse>(
    'GET',
    `/transactions/suggestions?q=${encodeURIComponent(query)}`,
  )
}
