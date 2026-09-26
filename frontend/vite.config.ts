import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

// 本地开发代理目标与端口可通过 frontend/.env.development 覆盖（VITE_DEV_PROXY_TARGET / VITE_DEV_PORT）。
// 生产构建不走 dev server，接口地址由后端 myconfig 经 GET /api/config 下发，不受此处影响。
export default defineConfig(({ mode }) => {
  // envDir 传 '.'，由 vite 按当前工作目录解析，避免依赖 node 类型定义
  const env = loadEnv(mode, '.', 'VITE_')
  const proxyTarget = env.VITE_DEV_PROXY_TARGET || 'http://127.0.0.1:8000'
  const port = Number(env.VITE_DEV_PORT || 5173)

  return {
    plugins: [vue()],
    server: {
      host: '0.0.0.0',
      port,
      allowedHosts: true,
      proxy: {
        '/api': {
          target: proxyTarget,
          changeOrigin: true,
        },
        // 门户背景图等上传资源由后端托管
        '/uploads': {
          target: proxyTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
