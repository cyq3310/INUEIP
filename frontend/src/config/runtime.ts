import axios from 'axios'

/**
 * 运行时配置：应用启动时从后端 GET /api/config 拉取，前后端共用同一份 backend/config/myconfig.php。
 * 接口不可用时使用内置默认值降级，不阻断首屏渲染。
 */

export interface RuntimeConfig {
  /** 环境标识：local | staging | production */
  env: string
  site: { name: string; version: string; copyright: string; icp: string }
  /** api.base_url 为最终使用的接口根地址；api.timeout 单位毫秒 */
  api: { base_url: string; timeout: number }
  web: { base_url: string }
  resource: { mode: 'local' | 'cdn'; base_url: string }
  skill: { source: 'local' | 'remote' | 'api'; use_mock: boolean; local_path: string; remote_base_url: string }
  portal: { page_size: number }
}

/** 内置默认值：与后端 myconfig 缺省值保持一致，仅作降级使用 */
const DEFAULT_CONFIG: RuntimeConfig = {
  env: 'production',
  site: { name: '', version: '', copyright: '', icp: '' },
  api: { base_url: '/api', timeout: 15000 },
  web: { base_url: '' },
  resource: { mode: 'local', base_url: '' },
  skill: { source: 'local', use_mock: false, local_path: '/uploads/skills', remote_base_url: '' },
  portal: { page_size: 10 },
}

const CONFIG_URL = '/api/config'

let cached: RuntimeConfig = { ...DEFAULT_CONFIG }

/** 用后端返回值覆盖默认值，缺字段保持默认，避免结构不完整导致前端报错 */
function normalize(data: Partial<RuntimeConfig>): RuntimeConfig {
  const apiTimeout = Number(data?.api?.timeout ?? 0)
  return {
    env: data.env ?? DEFAULT_CONFIG.env,
    site: { ...DEFAULT_CONFIG.site, ...(data.site ?? {}) },
    api: {
      // 后端 myconfig.deploy.api.timeout 单位为秒，前端统一用毫秒
      base_url: data.api?.base_url ?? DEFAULT_CONFIG.api.base_url,
      timeout: apiTimeout > 0 ? apiTimeout * 1000 : DEFAULT_CONFIG.api.timeout,
    },
    web: { ...DEFAULT_CONFIG.web, ...(data.web ?? {}) },
    resource: { ...DEFAULT_CONFIG.resource, ...(data.resource ?? {}) },
    skill: { ...DEFAULT_CONFIG.skill, ...(data.skill ?? {}) },
    portal: { ...DEFAULT_CONFIG.portal, ...(data.portal ?? {}) },
  }
}

/**
 * 确定实际使用的接口根地址：
 * - 相对路径（如 /api）直接使用；
 * - 绝对地址与当前页面同源时使用；
 * - 跨源地址在生产直接使用（依赖后端 CORS 配置），开发环境仍走 vite 代理。
 */
function resolveApiBase(baseUrl: string): string {
  if (!baseUrl) return DEFAULT_CONFIG.api.base_url
  if (!/^https?:\/\//i.test(baseUrl)) return baseUrl
  try {
    const url = new URL(baseUrl)
    if (url.origin === window.location.origin) return baseUrl
    return import.meta.env.DEV ? DEFAULT_CONFIG.api.base_url : baseUrl
  } catch {
    return DEFAULT_CONFIG.api.base_url
  }
}

/** 应用启动阶段调用一次；失败返回内置默认值 */
export async function loadRuntimeConfig(): Promise<RuntimeConfig> {
  try {
    const res = await axios.get(CONFIG_URL, { timeout: 8000, baseURL: '' })
    if (res.data?.code === 0 && res.data?.data) {
      const cfg = normalize(res.data.data)
      cfg.api.base_url = resolveApiBase(cfg.api.base_url)
      cached = cfg
    }
  } catch {
    // 配置接口不可用时静默降级，沿用内置默认值
  }
  return cached
}

/** 读取已缓存的配置（未加载完成时为默认值） */
export function getRuntimeConfig(): RuntimeConfig {
  return cached
}

/** 资源地址解析：CDN 模式拼资源根地址，local 模式原样返回库中相对路径 */
export function resolveAssetUrl(path: string): string {
  if (!path) return ''
  if (/^https?:\/\//i.test(path)) return path
  const { mode, base_url } = cached.resource
  if (mode === 'cdn' && base_url) {
    return base_url.replace(/\/+$/, '') + (path.startsWith('/') ? path : `/${path}`)
  }
  return path
}

/** skill 是否走 mock：VITE_USE_MOCK 可强制覆盖，否则取后端 myconfig.skill.use_mock */
export function useSkillMock(): boolean {
  const override = import.meta.env.VITE_USE_MOCK as string | undefined
  if (override === 'true') return true
  if (override === 'false') return false
  return cached.skill.use_mock
}
