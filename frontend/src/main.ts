import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
import 'element-plus/dist/index.css'
import './index.css'
import App from './App.vue'
import router from './router'
import { loadRuntimeConfig } from './config/runtime'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(ElementPlus, { locale: zhCn })

// 挂载前拉取后端下发的运行时配置（backend/config/myconfig.php 白名单项）；
// 拉取失败时沿用内置默认值，不阻断应用启动
loadRuntimeConfig().finally(() => {
  app.mount('#app')
})
