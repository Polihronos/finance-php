import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { setToken } from '@/api/client'
import * as authApi from '@/api/auth'
import { useCategoriesStore } from '@/stores/categories'
import { useTransactionsStore } from '@/stores/transactions'

const STORAGE_KEY = 'token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(STORAGE_KEY))
  setToken(token.value)

  const isLoggedIn = computed(() => token.value !== null)

  function saveToken(newToken: string | null): void {
    token.value = newToken
    setToken(newToken)
    if (newToken === null) {
      localStorage.removeItem(STORAGE_KEY)
      useTransactionsStore().clear()
      useCategoriesStore().clear()
    } else {
      localStorage.setItem(STORAGE_KEY, newToken)
    }
  }

  async function login(email: string, password: string): Promise<void> {
    const answer = await authApi.login(email, password)
    saveToken(answer.token)
  }

  async function register(name: string, email: string, password: string): Promise<void> {
    const answer = await authApi.register(name, email, password)
    saveToken(answer.token)
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      saveToken(null)
    }
  }

  function forget(): void {
    saveToken(null)
  }

  return { token, isLoggedIn, login, register, logout, forget }
})
