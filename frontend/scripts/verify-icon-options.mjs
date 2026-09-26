/**
 * 校验 src/config/icon-options.ts 中的图标名在 lucide-vue-next 中真实存在。
 * 用法：node scripts/verify-icon-options.mjs
 * 以运行时导入的导出表为准（lucide 保留大量历史别名，文件名校验会误报）。
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const source = fs.readFileSync(path.join(root, 'src/config/icon-options.ts'), 'utf8')
const icons = await import('lucide-vue-next')

const entries = [...source.matchAll(/\{\s*name:\s*'([^']+)',\s*label:\s*'([^']+)'\s*\}/g)].map((m) => ({ name: m[1], label: m[2] }))
const names = entries.map((e) => e.name)

const invalid = names.filter((n) => !(n in icons))
const duplicated = names.filter((n, i) => names.indexOf(n) !== i)
const noLabel = entries.filter((e) => !e.label.trim()).map((e) => e.name)

console.log(`图标总数：${names.length}`)
if (duplicated.length) console.log(`重复项：${[...new Set(duplicated)].join(', ')}`)
if (noLabel.length) console.log(`缺少中文标签：${noLabel.join(', ')}`)
if (invalid.length) console.log(`lucide 中不存在：${invalid.join(', ')}`)

if (invalid.length || duplicated.length || noLabel.length) {
  console.error('\n校验未通过，请修正上述图标名')
  process.exit(1)
}
console.log('全部图标名校验通过')
