<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft, ChevronDown, Image as ImageIcon, KeyRound, LogOut, Megaphone, ShieldCheck, Sparkles, Tags, Users, Waves, Workflow } from 'lucide-vue-next'
import { useUserStore } from '../stores/user'
import ChangePasswordDialog from '../components/ChangePasswordDialog.vue'

const store = useUserStore()
const route = useRoute()
const router = useRouter()

const menus = [
  { path: '/admin/users', title: '账号管理', icon: Users, perm: 'system:user' },
  { path: '/admin/roles', title: '角色权限', icon: ShieldCheck, perm: 'system:role' },
  { path: '/admin/app-links', title: '应用链接', icon: Workflow, perm: 'system:app-link' },
  { path: '/admin/announcements', title: '公告管理', icon: Megaphone, perm: 'system:announcement' },
  { path: '/admin/portal-themes', title: '门户背景', icon: ImageIcon, perm: 'system:portal-theme' },
  { path: '/admin/ai-skills', title: 'AI Skill 管理', icon: Sparkles, perm: 'ai-skill:manage-all' },
  { path: '/admin/ai-skill-categories', title: 'Skill 分类', icon: Tags, perm: 'ai-skill:category' },
]

const visibleMenus = computed(() => menus.filter((m) => store.hasPermission(m.perm)))
const nickname = computed(() => store.profile?.nickname || store.profile?.username || '')
const activePath = computed(() => route.path)

const changePwdRef = ref<InstanceType<typeof ChangePasswordDialog>>()

const handleCommand = (command: string) => {
  if (command === 'change-password') {
    changePwdRef.value?.open()
  } else if (command === 'logout') {
    ElMessageBox.confirm('确定要退出登录吗？', '退出登录', { type: 'warning' })
      .then(async () => {
        await store.logout()
        router.push('/login')
      })
      .catch(() => undefined)
  }
}

/** 改密成功后清除 Token 并跳转登录页，避免旧凭证在有效期内继续可用 */
const onPasswordChanged = async () => {
  ElMessage.success('密码修改成功，请重新登录')
  await store.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex min-h-screen bg-page">
    <aside class="fixed inset-y-0 left-0 z-30 flex w-56 flex-col border-r border-[#E6EBF5] bg-white">
      <div class="flex h-16 items-center gap-2.5 border-b border-[#F0F3FA] px-5">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-[#5B8FF9] to-[#7A6FF0] text-white">
          <Waves :size="17" />
        </div>
        <span class="text-[15px] font-semibold text-ink">吟游廊 EIP平台</span>
      </div>

      <nav class="flex-1 space-y-1 px-3 py-4">
        <button
          v-for="menu in visibleMenus"
          :key="menu.path"
          type="button"
          class="flex w-full items-center gap-2.5 rounded-lg px-3.5 py-2.5 text-sm transition-all duration-200 cursor-pointer"
          :class="
            activePath === menu.path
              ? 'bg-gradient-to-r from-[#5B8FF9] to-[#7A6FF0] font-medium text-white shadow-md'
              : 'text-ink-sub hover:bg-[#F2F5FC] hover:text-ink'
          "
          @click="router.push(menu.path)"
        >
          <component :is="menu.icon" :size="17" />
          {{ menu.title }}
        </button>
      </nav>

      <div class="border-t border-[#F0F3FA] p-3">
        <button
          type="button"
          class="flex w-full items-center gap-2.5 rounded-lg px-3.5 py-2.5 text-sm text-ink-sub transition-colors hover:bg-[#F2F5FC] hover:text-ink cursor-pointer"
          @click="router.push('/')"
        >
          <ArrowLeft :size="17" />
          返回门户首页
        </button>
      </div>
    </aside>

    <div class="ml-56 flex min-h-screen flex-1 flex-col">
      <header class="fixed left-56 right-0 top-0 z-20 flex h-16 items-center justify-between border-b border-[#E6EBF5] bg-white/85 px-6 backdrop-blur">
        <h1 class="text-base font-medium text-ink">{{ route.meta.title }}</h1>
        <el-dropdown trigger="click" @command="handleCommand">
          <div class="flex cursor-pointer items-center gap-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-[#7A6FF0] to-[#5B8FF9] text-xs font-medium text-white">
              {{ nickname.slice(0, 1) }}
            </div>
            <span class="text-sm text-ink">{{ nickname }}</span>
            <ChevronDown :size="14" class="text-ink-mute" />
          </div>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="change-password">
                <KeyRound :size="15" class="mr-1.5 inline-block align-[-2px]" />修改密码
              </el-dropdown-item>
              <el-dropdown-item command="logout" divided>
                <LogOut :size="15" class="mr-1.5 inline-block align-[-2px]" />退出登录
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </header>

      <main class="mt-16 flex-1 p-6">
        <router-view />
      </main>
    </div>

    <ChangePasswordDialog ref="changePwdRef" @success="onPasswordChanged" />
  </div>
</template>
