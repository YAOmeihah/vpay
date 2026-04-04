const Layout = () => import("@/layout/index.vue");

export default {
  path: "/orders",
  name: "Orders",
  component: Layout,
  redirect: "/orders/index",
  meta: {
    icon: "ri:file-list-3-line",
    title: "订单列表",
    rank: 30
  },
  children: [
    {
      path: "/orders/index",
      name: "OrderList",
      component: () => import("@/views/orders/index.vue"),
      meta: {
        title: "订单列表"
      }
    }
  ]
} satisfies RouteConfigsTable;
