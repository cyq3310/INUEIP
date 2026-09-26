import { defineStore } from 'pinia'
import { ref } from 'vue'
import { portalApi } from '../api'
import type { PortalThemeConfig } from '../types'

export const usePortalStore = defineStore('portal', () => {
  /** 门户主题配置（按模块存放，如 greeting）；回首页时同步读取，避免背景闪烁与重复请求 */
  const theme = ref<Record<string, PortalThemeConfig> | null>(null)

  /** 仅当未缓存时才请求接口；已缓存则同步返回。返回最新值便于调用方 immediate 使用 */
  const ensureTheme = async (): Promise<Record<string, PortalThemeConfig> | null> => {
    if (theme.value) return theme.value
    const res = await portalApi.theme()
    theme.value = res.data
    return theme.value
  }

  return { theme, ensureTheme }
})
