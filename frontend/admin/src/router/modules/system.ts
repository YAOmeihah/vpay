const Layout = () => import("@/layout/index.vue");

export default {
  path: "/system",
  name: "System",
  component: Layout,
  redirect: "/system/settings",
  meta: {
    icon: "ri:settings-3-line",
    title: "系统管理",
    rank: 10
  },
  children: [
    {
      path: "/system/settings",
      name: "SystemSettings",
      component: () => import("@/views/system/settings/index.vue"),
      meta: { title: "系统设置" }
    },
    {
      path: "/system/monitor",
      name: "MonitorSettings",
      component: () => import("@/views/system/monitor/index.vue"),
      meta: { title: "监控端设置" }
    }
  ]
} satisfies RouteConfigsTable;
