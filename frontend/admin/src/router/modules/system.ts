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
    },
    {
      path: "/system/terminals",
      name: "TerminalManagement",
      component: () => import("@/views/system/terminals/index.vue"),
      meta: { title: "终端管理" }
    },
    {
      path: "/system/terminals/:terminalId",
      name: "TerminalChannels",
      component: () => import("@/views/system/terminals/detail.vue"),
      meta: { title: "通道管理", showLink: false }
    }
  ]
} satisfies RouteConfigsTable;
