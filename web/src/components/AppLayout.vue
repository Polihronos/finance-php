<script setup lang="ts">
import { LogOut, ReceiptText, Tags } from '@lucide/vue'
import { useRoute, useRouter } from 'vue-router'

import dog from '@/assets/dog.webp'
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
    <RouterLink to="/" class="home">
      <img :src="dog" alt="MyApp" width="58" height="58" />
    </RouterLink>
    <div class="actions">
      <PeriodToggle v-if="route.meta.periodToggle" />
      <!-- <AppButton :icon="LogOut" label="Log out" icon-only variant="quiet" @click="logOut" /> -->
    </div>
  </header>
  <RouterView />
  <nav class="tabs" aria-label="Pages">
    <RouterLink :to="{ name: 'transactions' }">
      <ReceiptText />
      Transactions
    </RouterLink>
    <RouterLink :to="{ name: 'categories' }">
      <Tags />
      Categories
    </RouterLink>
  </nav>
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

.home {
  display: flex;
  border-radius: 50%;
}

.home:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

.actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

main {
  padding-bottom: 6rem;
}

.tabs {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  max-width: 28rem;
  margin: 0 auto;
  padding: 0.25rem 1rem calc(0.25rem + env(safe-area-inset-bottom));
  border-top: 1px solid color-mix(in srgb, var(--text), transparent 85%);
  background: var(--background);
}

.tabs a {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.125rem;
  padding: 0.5rem;
  font-size: 0.75rem;
  color: color-mix(in srgb, var(--text), transparent 35%);
  text-decoration: none;
}

.tabs a:hover {
  text-decoration: none;
}

.tabs a[aria-current='page'] {
  color: var(--primary);
}

.tabs svg {
  width: 1.5rem;
  height: 1.5rem;
}
</style>
