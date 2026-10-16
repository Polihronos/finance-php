<script setup lang="ts">
import { LogOut } from '@lucide/vue'
import { useRoute, useRouter } from 'vue-router'

import AppButton from '@/components/AppButton.vue'
import PeriodToggle from '@/components/PeriodToggle.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

async function logOut(): Promise<void> {
  await auth.logout().catch(() => {})
  await router.push({ name: 'login' })
}
</script>

<template>
  <header>
    <strong>MyApp</strong>
    <div class="actions">
      <PeriodToggle v-if="route.meta.periodToggle" />
      <!-- <AppButton :icon="LogOut" label="Log out" icon-only variant="quiet" @click="logOut" /> -->
    </div>
  </header>
  <RouterView />
</template>

<style scoped>
header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 28rem;
  margin: 0 auto;
  padding: 1rem 1rem 0;
}

.actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
</style>
