<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Lock, User, Waves } from 'lucide-vue-next'
import { useUserStore } from '../stores/user'

const store = useUserStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ username: '', password: '' })
const loading = ref(false)

const submit = () => {
  if (!form.username || !form.password || loading.value) return
  loading.value = true
  store
    .login(form.username, form.password)
    .then(() => {
      const redirect = (route.query.redirect as string) || '/'
      router.push(redirect)
    })
    .catch(console.error)
    .finally(() => {
      loading.value = false
    })
}
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-[#EEF2FB] via-[#EDEAFB] to-[#E3EDFB]">
    <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-[#5B8FF9]/20 blur-3xl" />
    <div class="pointer-events-none absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-[#7A6FF0]/20 blur-3xl" />

    <div class="relative w-[400px] rounded-3xl border border-white/70 bg-white/70 p-10 shadow-card-hover backdrop-blur-xl">
      <div class="mb-8 flex flex-col items-center">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#5B8FF9] to-[#7A6FF0] text-white shadow-lg">
          <Waves :size="28" />
        </div>
        <h1 class="text-2xl font-semibold text-ink">吟游廊 EIP平台</h1>
        <p class="mt-1.5 text-sm text-ink-mute">企业信息门户 · 欢迎登录</p>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div class="flex items-center gap-3 rounded-xl border border-[#E3E9F5] bg-white/90 px-4 transition-colors focus-within:border-primary">
          <User :size="17" class="shrink-0 text-ink-mute" />
          <input
            v-model="form.username"
            type="text"
            placeholder="请输入账号"
            autocomplete="username"
            class="h-11 w-full border-none bg-transparent text-sm text-ink outline-none placeholder:text-ink-mute"
          />
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-[#E3E9F5] bg-white/90 px-4 transition-colors focus-within:border-primary">
          <Lock :size="17" class="shrink-0 text-ink-mute" />
          <input
            v-model="form.password"
            type="password"
            placeholder="请输入密码"
            autocomplete="current-password"
            class="h-11 w-full border-none bg-transparent text-sm text-ink outline-none placeholder:text-ink-mute"
          />
        </div>
        <button
          type="submit"
          :disabled="loading || !form.username || !form.password"
          class="h-11 w-full rounded-xl bg-gradient-to-r from-[#5B8FF9] to-[#7A6FF0] text-sm font-medium text-white shadow-md transition-all duration-300 hover:shadow-lg hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ loading ? '登录中…' : '登 录' }}
        </button>
      </form>
    </div>
  </div>
</template>
