import '@fontsource-variable/montserrat'
import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { setUnauthorizedHandler } from './api/client'
import { useAuthStore } from './stores/auth'



const app = createApp(App)

app.use(createPinia())
app.use(router)

const auth = useAuthStore()
setUnauthorizedHandler(() => {
  auth.forget()
  router.push({ name: 'login' })
})

app.mount('#app')
