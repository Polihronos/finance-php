<script setup lang="ts">
import { UserPlus } from '@lucide/vue'
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import AppButton from '@/components/AppButton.vue'
import AppError from '@/components/AppError.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const loading = ref(false)

async function submit(): Promise<void> {
  error.value = null
  loading.value = true
  try {
    await auth.register(name.value, email.value, password.value)
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
    <h1>Create account</h1>
    <form @submit.prevent="submit">
      <label>
        Name
        <input v-model="name" type="text" autocomplete="name" required />
      </label>
      <label>
        Email
        <input v-model="email" type="email" autocomplete="email" required />
      </label>
      <label>
        Password
        <PasswordInput
          v-model="password"
          autocomplete="new-password"
          required
          minlength="8"
          maxlength="36"
        />
      </label>
      <AppError v-if="error">{{ error }}</AppError>
      <AppButton :icon="UserPlus" label="Create account" type="submit" :disabled="loading" />
    </form>
    <p>Already have an account? <RouterLink :to="{ name: 'login' }">Log in</RouterLink></p>
  </main>
</template>
