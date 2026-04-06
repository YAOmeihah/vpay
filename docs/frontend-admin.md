# VPay Admin Frontend

新后台现在是唯一管理端入口，访问地址统一为 `/console/`。旧的 `public/admin/*.html`、`/getMenu` 和配套 Layui 静态页已经移除。

## Development

1. `php think run`
2. `cd frontend/admin`
3. `pnpm install`
4. `pnpm dev`

## Build

1. `cd frontend/admin`
2. `pnpm build`
3. Visit `/console/`

## Backend endpoints used

- `/login`
- `/admin/index/profile`
- `/admin/index/logout`
- `/admin/index/getMain`
- `/admin/index/getSettings`
- `/admin/index/saveSetting`
- `/admin/index/addPayQrcode`
- `/admin/index/getPayQrcodes`
- `/admin/index/delPayQrcode`
- `/admin/index/getOrders`
- `/admin/index/delOrder`
- `/admin/index/setBd`
- `/admin/index/delGqOrder`
- `/admin/index/delLastOrder`
- `/enQrcode`

## Notes

- 开发代理不再转发 `/getMenu`，菜单由 Vue 路由静态定义。
- `public/console/` 是本地构建产物目录，不纳入 Git 跟踪。
- 旧 `api.html` 已由 [payment-api.md](./payment-api.md) 取代。

## Root Portal

- `/` 现在是系统门户页，用于分流后台入口和接口文档入口
- `/console/` 仍然是唯一管理后台地址
- 根路径门户页不承担登录逻辑，只承担导航和能力说明
