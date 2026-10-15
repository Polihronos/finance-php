<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue'
import { ref } from 'vue'

import AppButton from '@/components/AppButton.vue'

defineOptions({ inheritAttrs: false })

const password = defineModel<string>({ required: true })
const visible = ref(false)
</script>

<template>
  <div class="password-input">
    <input v-bind="$attrs" v-model="password" :type="visible ? 'text' : 'password'" />
    <AppButton
      :icon="visible ? EyeOff : Eye"
      :label="visible ? 'Hide password' : 'Show password'"
      icon-only
      variant="quiet"
      @click="visible = !visible"
    />
  </div>
</template>

<style scoped>
.password-input {
  position: relative;
  display: grid;
}

input {
  padding-right: 2.75rem;
}

button {
  position: absolute;
  top: 0;
  right: 0;
}
</style>
