<script setup lang="ts">
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { LayoutDashboard, LogOut, Waves, Home, Megaphone, Sparkles } from 'lucide-vue-next'
import { useUserStore } from '../stores/user'

const store = useUserStore()
const router = useRouter()
const route = useRoute()

const nickname = computed(() => store.profile?.nickname || store.profile?.username || '')

const isHome = computed(() => route.path === '/')
const isAnnouncements = computed(() => route.path.startsWith('/announcements'))
const isSkills = computed(() => route.path.startsWith('/skills'))

const goHome = () => router.push('/')

const goAnnouncements = () => router.push('/announcements')

const goSkills = () => router.push('/skills')

const handleCommand = (command: string) => {
  if (command === 'admin') {
    router.push('/admin')
  } else if (command === 'logout') {
    ElMessageBox.confirm('确定要退出登录吗？', '退出登录', { type: 'warning' })
      .then(async () => {
        await store.logout()
        router.push('/login')
      })
      .catch(() => undefined)
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F7FA]">
    <header class="fixed inset-x-0 top-0 z-30 h-16 bg-white shadow-sm">
      <div class="mx-auto flex h-full w-full max-w-[1920px] items-center justify-between px-8">
        <div class="flex items-center gap-3 cursor-pointer" @click="router.push('/')">
          <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#5B8FF9] to-[#7A6FF0] text-white shadow-md">
            <Waves :size="20" />
          </div>
          <span class="text-2xl font-bold tracking-wide text-ink">吟游廊 <span class="text-primary-deep">EIP平台</span></span>
        </div>

        <nav class="ml-6 flex items-center gap-1">
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-[18px] font-medium transition-colors"
            :class="isHome ? 'text-primary bg-blue-50' : 'text-ink-sub hover:bg-gray-50'"
            @click="goHome"
          >
            <Home :size="16" />
            首页
          </button>
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-[18px] font-medium transition-colors"
            :class="isAnnouncements ? 'text-primary bg-blue-50' : 'text-ink-sub hover:bg-gray-50'"
            @click="goAnnouncements"
          >
            <Megaphone :size="16" />
            公告
          </button>
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-[18px] font-medium transition-colors"
            :class="isSkills ? 'text-primary bg-blue-50' : 'text-ink-sub hover:bg-gray-50'"
            @click="goSkills"
          >
            <Sparkles :size="16" />
            AI Skill
          </button>
        </nav>

        <div class="flex items-center gap-4">
          <el-dropdown trigger="click" @command="handleCommand">
            <div class="flex cursor-pointer items-center gap-2 rounded-full py-1 pl-1 pr-3 transition-colors hover:bg-gray-50">
              <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-[#7A6FF0] to-[#5B8FF9] text-sm font-medium text-white">
                {{ nickname.slice(0, 1) }}
              </div>
              <span class="text-sm text-ink">{{ nickname || '头像' }}</span>
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item v-if="store.isAdmin" command="admin">
                  <LayoutDashboard :size="15" class="mr-1.5 inline-block align-[-2px]" />后台管理
                </el-dropdown-item>
                <el-dropdown-item command="logout" divided>
                  <LogOut :size="15" class="mr-1.5 inline-block align-[-2px]" />退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </div>
    </header>

    <main class="pt-16">
      <router-view />
    </main>
  </div>
</template>
