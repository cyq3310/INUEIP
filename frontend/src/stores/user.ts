import { defineStore } from 'pinia'
import { authApi } from '../api'
import type { UserProfile } from '../types'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: localStorage.getItem('inue_token') || '',
    profile: null as UserProfile | null,
  }),
  getters: {
    isLoggedIn: (state) => !!state.token,
    /** 拥有任一后台权限码即视为管理员 */
    isAdmin: (state) => !!state.profile && (state.profile.isSuperAdmin || state.profile.permissions.length > 0),
  },
  actions: {
    async login(username: string, password: string) {
      const res = await authApi.login(username, password)
      this.token = res.data.token
      this.profile = res.data.user
      localStorage.setItem('inue_token', res.data.token)
    },
    async fetchProfile() {
      const res = await authApi.profile()
      this.profile = res.data
    },
    async logout() {
      authApi.logout().catch(console.error)
      this.token = ''
      this.profile = null
      localStorage.removeItem('inue_token')
    },
    /** 是否拥有指定权限码 */
    hasPermission(code: string): boolean {
      if (!this.profile) return false
      return this.profile.isSuperAdmin || this.profile.permissions.includes(code)
    },
  },
})
