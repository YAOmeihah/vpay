const Layout = () => import("@/layout/index.vue");

export default {
  path: "/",
  name: "Dashboard",
  component: Layout,
  redirect: "/dashboard",
  meta: {
    icon: "ep/home-filled",
    title: "控制台",
    rank: 0
  }
} satisfies RouteConfigsTable;
