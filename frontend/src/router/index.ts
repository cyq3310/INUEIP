import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '../stores/user'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'Login',
      component: () => import('../views/Login.vue'),
      meta: { title: '登录' },
    },
    {
      path: '/',
      component: () => import('../layout/PortalLayout.vue'),
      children: [
        { path: '', name: 'Home', component: () => import('../views/portal/Home.vue'), meta: { title: '首页' } },
        { path: 'announcements', name: 'Announcements', component: () => import('../views/portal/Announcements.vue'), meta: { title: '公告' } },
        { path: 'skills', name: 'PortalSkills', component: () => import('../views/portal/Skills.vue'), meta: { title: 'AI Skill 广场' } },
        { path: 'skills/my', name: 'PortalMySkills', component: () => import('../views/portal/MySkills.vue'), meta: { title: '我的 Skill' } },
      ],
    },
    {
      path: '/admin',
      component: () => import('../layout/AdminLayout.vue'),
      redirect: '/admin/users',
      children: [
        { path: 'users', name: 'AdminUsers', component: () => import('../views/admin/Users.vue'), meta: { title: '账号管理', perm: 'system:user' } },
        { path: 'roles', name: 'AdminRoles', component: () => import('../views/admin/Roles.vue'), meta: { title: '角色权限', perm: 'system:role' } },
        { path: 'app-links', name: 'AdminAppLinks', component: () => import('../views/admin/AppLinks.vue'), meta: { title: '应用链接', perm: 'system:app-link' } },
        { path: 'announcements', name: 'AdminAnnouncements', component: () => import('../views/admin/Announcements.vue'), meta: { title: '公告管理', perm: 'system:announcement' } },
        { path: 'portal-themes', name: 'AdminPortalThemes', component: () => import('../views/admin/PortalTheme.vue'), meta: { title: '门户背景', perm: 'system:portal-theme' } },
        { path: 'ai-skills', name: 'AdminAiSkills', component: () => import('../views/admin/AiSkills.vue'), meta: { title: 'AI Skill 管理', perm: 'ai-skill:manage-all' } },
        {
          path: 'ai-skill-categories',
          name: 'AdminAiSkillCategories',
          component: () => import('../views/admin/AiSkillCategories.vue'),
          meta: { title: 'Skill 分类', perm: 'ai-skill:category' },
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  document.title = to.meta.title ? `${to.meta.title} - 吟游廊 EIP平台` : '吟游廊 EIP平台'

  if (to.path === '/login') {
    return true
  }

  const store = useUserStore()
  if (!store.isLoggedIn) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (!store.profile) {
    await store.fetchProfile().catch(console.error)
  }
  // 获取档案失败（如 Token 失效）已在拦截器中跳转登录
  if (!store.profile) {
    return false
  }

  if (to.path.startsWith('/admin')) {
    if (!store.isAdmin) {
      return { path: '/' }
    }
    const perm = to.meta.perm as string | undefined
    if (perm && !store.hasPermission(perm)) {
      // 无该菜单权限时落到第一个有权限的后台页
      const first = (router.options.routes.find((r) => r.path === '/admin')?.children ?? []).find(
        (r) => r.meta?.perm && store.hasPermission(r.meta.perm as string),
      )
      return first ? { path: `/admin/${first.path}` } : { path: '/' }
    }
  }

  return true
})

export default router
