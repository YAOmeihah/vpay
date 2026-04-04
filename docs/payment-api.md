# VPay Payment API

这份文档替代旧的 `public/api.html`。接口说明以当前 ThinkPHP 路由和控制器实现为准。

## Native endpoints

### `POST|GET /createOrder`

用途：创建支付订单。

请求参数：

- `payId`：商户订单号，必填，不可重复。
- `type`：支付方式，必填，`1` 为微信，`2` 为支付宝。
- `price`：订单金额，必填，必须大于 `0` 且小于 `1000000`。
- `sign`：创建订单签名，必填，算法为 `md5(payId + param + type + price + key)`。
- `param`：自定义透传参数，可选。
- `notifyUrl`：异步通知地址，可选。
- `returnUrl`：同步跳转地址，可选。
- `isHtml`：为 `1` 时直接跳转支付页，否则返回 JSON，默认 `0`。

成功返回的核心字段：

- `payId`
- `orderId`
- `payType`
- `price`
- `reallyPrice`
- `payUrl`
- `isAuto`

### `POST|GET /getOrder`

用途：查询订单详情。

请求参数：

- `orderId`：云端订单号，必填。

成功返回的核心字段：

- `payId`
- `orderId`
- `payType`
- `price`
- `reallyPrice`
- `payUrl`
- `isAuto`
- `state`
- `timeOut`
- `date`

### `POST|GET /checkOrder`

用途：查询订单是否支付完成。

请求参数：

- `orderId`：云端订单号，必填。

返回说明：

- 未支付或已过期时返回失败消息。
- 支付成功时返回同步跳转 URL。

### `POST|GET /closeOrder`

用途：关闭未支付订单。

请求参数：

- `orderId`：云端订单号，必填。
- `sign`：简单签名，必填，算法为 `md5(orderId + key)`。

## Monitor endpoints

### `POST|GET /getState`

用途：查询监控端状态。

请求参数：

- `t`：时间戳或约定字符串，参与签名。
- `sign`：简单签名，算法为 `md5(t + key)`。

返回字段：

- `lastheart`
- `lastpay`
- `jkstate`

### `POST|GET /appHeart`

用途：监控端心跳上报。

请求参数：

- `t`
- `sign`：算法为 `md5(t + key)`。

### `POST|GET /appPush`

用途：监控端上报收款结果。

请求参数：

- `t`
- `type`
- `price`
- `sign`：算法为 `md5(type + price + t + key)`。

### `POST|GET /closeEndOrder`

用途：清理超时未完成订单。

## Merchant callback

订单支付成功后，系统会向 `notifyUrl` 发送回调，并在 `returnUrl` 上拼接同一组业务字段。

回调字段：

- `payId`：商户订单号。
- `param`：创建订单时传入的自定义参数。
- `type`：支付方式。
- `price`：订单金额。
- `reallyPrice`：实际支付金额。
- `sign`：回调签名，算法为 `md5(payId + param + type + price + reallyPrice + key)`。

## Compatibility endpoints

以下兼容接口仍保留，供旧商户接入使用：

- `POST /mapi.php`
- `GET|POST /submit.php`
- `POST /api/pay/create`
- `POST /api/pay/submit`

## Verification source

- 路由：`route/merchant.php`、`route/monitor.php`、`route/compat.php`
- 控制器：`app/controller/merchant/Order.php`、`app/controller/monitor/Monitor.php`
- 签名：`app/service/SignService.php`
