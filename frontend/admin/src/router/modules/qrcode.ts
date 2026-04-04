const Layout = () => import("@/layout/index.vue");

export default {
  path: "/qrcode",
  name: "Qrcode",
  component: Layout,
  redirect: "/qrcode/wechat/add",
  meta: {
    icon: "ri:qr-code-line",
    title: "二维码管理",
    rank: 20
  },
  children: [
    {
      path: "/qrcode/wechat/add",
      name: "WechatQrcodeAdd",
      component: () => import("@/views/qrcode/wechat-add/index.vue"),
      meta: { title: "微信二维码新增" }
    },
    {
      path: "/qrcode/wechat/list",
      name: "WechatQrcodeList",
      component: () => import("@/views/qrcode/wechat-list/index.vue"),
      meta: { title: "微信二维码管理" }
    },
    {
      path: "/qrcode/alipay/add",
      name: "AlipayQrcodeAdd",
      component: () => import("@/views/qrcode/alipay-add/index.vue"),
      meta: { title: "支付宝二维码新增" }
    },
    {
      path: "/qrcode/alipay/list",
      name: "AlipayQrcodeList",
      component: () => import("@/views/qrcode/alipay-list/index.vue"),
      meta: { title: "支付宝二维码管理" }
    }
  ]
} satisfies RouteConfigsTable;
