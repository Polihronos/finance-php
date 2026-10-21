<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import dog from '@/assets/dog.webp'
import AppButton from '@/components/AppButton.vue'
import AppError from '@/components/AppError.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { useAuthStore } from '@/stores/auth'

import { LogIn } from '@lucide/vue'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const loading = ref(false)

async function submit(): Promise<void> {
  error.value = null
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    await router.push('/')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'cannot reach the server'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="centered">
    <h1 class="title">
      Log in
      <img :src="dog" alt="MyApp" width="48" height="48" />
    </h1>
    <form @submit.prevent="submit">
      <label>
        Email
        <input v-model="email" type="email" autocomplete="email" required />
      </label>
      <label>
        Password
        <PasswordInput v-model="password" autocomplete="current-password" required />
      </label>
      <AppError v-if="error">{{ error }}</AppError>
      <AppButton :icon="LogIn" label="Log in" type="submit" :disabled="loading" />
    </form>
    <p>No account yet? <RouterLink :to="{ name: 'register' }">Create one</RouterLink></p>
  </main>
</template>

<style scoped>
.title {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}
</style>
