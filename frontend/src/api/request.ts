import axios from 'axios'
import { ElMessage } from 'element-plus'
import { getRuntimeConfig } from '../config/runtime'

const request = axios.create({
  baseURL: '/api', // 缺省值；实际地址由运行时配置（后端 myconfig）下发
  timeout: 15000,
})

request.interceptors.request.use((config) => {
  // 接口根地址与超时统一取自后端 myconfig，避免前端写死 IP/端口
  const { api } = getRuntimeConfig()
  if (api.base_url) config.baseURL = api.base_url
  if (api.timeout > 0) config.timeout = api.timeout

  const token = localStorage.getItem('inue_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

request.interceptors.response.use(
  (response) => {
    const body = response.data
    if (body && body.code === 0) {
      return body
    }
    const msg = body?.msg || '请求失败'
    if (body?.code === 401) {
      localStorage.removeItem('inue_token')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    } else {
      ElMessage.error(msg)
    }
    return Promise.reject(new Error(msg))
  },
  (error) => {
    const msg = error.response?.data?.msg || error.message || '网络异常，请稍后重试'
    ElMessage.error(msg)
    return Promise.reject(new Error(msg))
  },
)

export default request
