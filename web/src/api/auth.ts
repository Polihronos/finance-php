import { request } from './client'
import type { AuthResponse, SuccessResponse } from './types'

export function register(name: string, email: string, password: string): Promise<AuthResponse> {
  return request<AuthResponse>('POST', '/register', { name, email, password })
}

export function login(email: string, password: string): Promise<AuthResponse> {
  return request<AuthResponse>('POST', '/login', { email, password })
}

export function logout(): Promise<SuccessResponse> {
  return request<SuccessResponse>('POST', '/logout')
}
