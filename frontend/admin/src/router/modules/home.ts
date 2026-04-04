const { VITE_HIDE_HOME } = import.meta.env;
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
  },
  children: [
    {
      path: "/dashboard",
      name: "DashboardHome",
      component: () => import("@/views/welcome/index.vue"),
      meta: {
        title: "控制台",
        showLink: VITE_HIDE_HOME === "true" ? false : true
      }
    }
  ]
} satisfies RouteConfigsTable;
