# VPay 商户对接文档

本文档面向接入 VPay 的商户系统开发者，只说明商户侧需要使用的接口、签名、回调和注意事项。文档已按当前代码实现逐项核对，代码对照见文末。

示例中的基础地址请替换为你的 VPay 站点域名：

```text
https://pay.example.com
```

## 1. 接入准备

1. 在 VPay 管理后台完成支付基础配置，确认通讯密钥不为空。
2. 配置默认异步回调地址 `notifyUrl` 和默认同步跳转地址 `returnUrl`，或者在每次下单时传入订单级地址。
3. 确认收款终端和对应支付通道可用，否则创建订单会失败。
4. 商户系统需要保存自己的商户订单号 `payId`，并保证唯一。

接口统一返回 JSON：

```json
{
  "code": 1,
  "msg": "成功",
  "data": null
}
```

`code=1` 表示成功，`code=-1` 表示失败，失败原因在 `msg` 中。

## 2. 支付流程

1. 商户系统创建本地订单，生成唯一的商户订单号 `payId`。
2. 商户按下单签名规则生成 `sign`，调用 `/createOrder`。
3. VPay 返回平台订单号 `orderId`、收款地址 `payUrl`、实际应付金额 `reallyPrice` 等信息。
4. 商户跳转用户到 VPay 支付页：

```text
https://pay.example.com/payPage/pay.html?orderId=平台订单号
```

5. 用户按页面展示的 `reallyPrice` 扫码付款。
6. VPay 匹配收款结果后，请求商户异步通知地址 `notifyUrl`。
7. 商户验签、校验金额、幂等更新订单状态，并返回 `success`。
8. VPay 支付页检测成功后调用 `/checkOrder`，拿到同步跳转 URL 并跳回商户页面。

异步通知是订单状态变更依据；同步跳转只用于给用户展示支付结果。

## 3. 签名规则

所有签名都使用小写 MD5。拼接时不加分隔符。

### 3.1 创建订单签名

```text
sign = md5(payId + param + type + price + key)
```

字段说明：

| 字段 | 说明 |
| --- | --- |
| `payId` | 商户订单号 |
| `param` | 商户透传参数，未传时按空字符串参与签名 |
| `type` | 支付方式，必须是 `1` 或 `2` |
| `price` | 订单金额，签名时必须使用请求中传入的原始金额字符串 |
| `key` | 管理后台的通讯密钥 |

示例：

```php
<?php
$key = 'your_sign_key';
$payId = 'M202604250001';
$param = 'user=10001';
$type = '1';
$price = '99.00';

$sign = md5($payId . $param . $type . $price . $key);
```

### 3.2 关闭订单签名

```text
sign = md5(orderId + key)
```

### 3.3 回调验签

异步通知和同步跳转都会携带以下字段：

```text
payId, param, type, price, reallyPrice, sign
```

验签规则：

```text
sign = md5(payId + param + type + price + reallyPrice + key)
```

验签时请使用收到的字段原文拼接，不要自行改变金额格式。

## 4. 创建订单

接口：

```text
POST|GET /createOrder
```

推荐使用 `POST` 和 `application/x-www-form-urlencoded`。

请求参数：

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `payId` | 是 | 商户订单号，必须唯一，最长 100 字符 |
| `type` | 是 | 支付方式：`1` 微信，`2` 支付宝 |
| `price` | 是 | 订单金额，必须大于 `0` 且小于 `1000000` |
| `sign` | 是 | 下单签名 |
| `param` | 否 | 商户透传参数，建议不超过 255 字符 |
| `notifyUrl` | 否 | 本订单异步通知地址，最长 1000 字符；不传则使用后台默认值 |
| `returnUrl` | 否 | 本订单同步跳转地址，最长 1000 字符；不传则使用后台默认值 |
| `isHtml` | 否 | 传 `1` 时输出跳转脚本，直接跳到 VPay 支付页；默认返回 JSON |

请求示例：

```bash
curl -X POST "https://pay.example.com/createOrder" \
  -d "payId=M202604250001" \
  -d "type=1" \
  -d "price=99.00" \
  -d "param=user=10001" \
  -d "notifyUrl=https://merchant.example.com/vpay/notify" \
  -d "returnUrl=https://merchant.example.com/order/result" \
  -d "sign=按签名规则生成的MD5"
```

成功响应示例：

```json
{
  "code": 1,
  "msg": "成功",
  "data": {
    "payId": "M202604250001",
    "orderId": "202604251830001234",
    "payType": 1,
    "price": "99.00",
    "reallyPrice": "99.01",
    "payUrl": "weixin://...",
    "isAuto": 1,
    "state": 0,
    "timeOut": "15",
    "date": 1777113000,
    "terminalId": 1,
    "channelId": 1,
    "terminalSnapshot": "默认终端",
    "channelSnapshot": "默认微信通道"
  }
}
```

返回字段说明：

| 字段 | 说明 |
| --- | --- |
| `payId` | 商户订单号 |
| `orderId` | VPay 平台订单号 |
| `payType` | 支付方式，`1` 微信，`2` 支付宝 |
| `price` | 商户下单金额 |
| `reallyPrice` | 实际应付金额，可能因防撞单被微调 |
| `payUrl` | 收款二维码或支付 URL |
| `isAuto` | `1` 表示通道固定收款 URL，需要用户手动输入金额；`0` 表示匹配到了固定金额二维码 |
| `state` | 订单状态，创建成功时为 `0` |
| `timeOut` | 订单有效期，单位为分钟，值来自后台配置 |
| `date` | 订单创建时间，Unix 秒级时间戳 |
| `terminalId` | 分配到的收款终端 ID |
| `channelId` | 分配到的支付通道 ID |
| `terminalSnapshot` | 下单时的终端名称快照 |
| `channelSnapshot` | 下单时的通道名称快照 |

重要：商户自定义收银台必须展示 `reallyPrice`，不要让用户按 `price` 支付。

常见失败：

| `msg` | 原因 |
| --- | --- |
| `签名错误` | 下单签名不正确 |
| `商户订单号已存在` | `payId` 重复 |
| `支付方式错误=>1|微信 2|支付宝` | `type` 不是 `1` 或 `2` |
| `订单金额格式错误` | `price` 不是合法金额 |
| `订单金额必须大于0` | 金额小于或等于 0 |
| `订单金额超出限制` | 金额大于或等于 1000000 |
| `当前无可用微信收款终端` | 微信终端或通道不可用 |
| `当前无可用支付宝收款终端` | 支付宝终端或通道不可用 |
| `请您先进入后台配置程序` | 已选通道没有配置收款 URL |
| `订单超出负荷，请稍后重试` | 当前通道同金额占位已满 |

## 5. 查询订单

接口：

```text
POST|GET /getOrder
```

请求参数：

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `orderId` | 是 | `/createOrder` 返回的平台订单号 |

请求示例：

```bash
curl "https://pay.example.com/getOrder?orderId=202604251830001234"
```

成功时返回订单信息。若订单不存在，返回：

```json
{
  "code": -1,
  "msg": "云端订单编号不存在",
  "data": null
}
```

订单状态：

| 状态值 | 含义 |
| --- | --- |
| `0` | 未支付 |
| `1` | 已支付 |
| `2` | 通知失败 |
| `-1` | 已过期 |
| `-2` | 已取消 |
| `-3` | 分配失败 |

## 6. 检查支付结果

接口：

```text
POST|GET /checkOrder
```

请求参数：

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `orderId` | 是 | `/createOrder` 返回的平台订单号 |

支付成功响应示例：

```json
{
  "code": 1,
  "msg": "成功",
  "data": "https://merchant.example.com/order/result?payId=M202604250001&param=user%3D10001&type=1&price=99.00&reallyPrice=99.01&sign=..."
}
```

未支付响应：

```json
{
  "code": -1,
  "msg": "订单未支付",
  "data": null
}
```

过期响应：

```json
{
  "code": -1,
  "msg": "订单已过期",
  "data": null
}
```

当前实现中，`/checkOrder` 只拦截 `state=0` 和 `state=-1`；其他状态会构建并返回同步跳转 URL。商户最终入账逻辑不要依赖同步跳转，应以异步通知验签后的结果为准。

## 7. 关闭订单

接口：

```text
POST|GET /closeOrder
```

请求参数：

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `orderId` | 是 | `/createOrder` 返回的平台订单号 |
| `sign` | 是 | `md5(orderId + key)` |

请求示例：

```bash
curl -X POST "https://pay.example.com/closeOrder" \
  -d "orderId=202604251830001234" \
  -d "sign=按关闭订单规则生成的MD5"
```

成功响应：

```json
{
  "code": 1,
  "msg": "成功",
  "data": null
}
```

只有未支付订单可以关闭。关闭后当前代码将订单状态更新为 `-1`。

## 8. 异步通知

支付成功后，VPay 会使用 HTTP GET 请求订单的 `notifyUrl`。订单未传 `notifyUrl` 时，使用后台默认异步回调地址。

通知字段：

| 字段 | 说明 |
| --- | --- |
| `payId` | 商户订单号 |
| `param` | 商户透传参数 |
| `type` | 支付方式，`1` 微信，`2` 支付宝 |
| `price` | 商户下单金额 |
| `reallyPrice` | 用户实际支付金额 |
| `sign` | 回调签名 |

通知示例：

```text
GET https://merchant.example.com/vpay/notify?payId=M202604250001&param=user%3D10001&type=1&price=99.00&reallyPrice=99.01&sign=...
```

商户处理要求：

1. 使用回调验签规则校验 `sign`。
2. 使用 `payId` 查询商户订单。
3. 校验订单未被处理过，做好幂等。
4. 校验订单金额，注意实际付款金额是 `reallyPrice`。
5. 更新商户订单为已支付。
6. 响应正文必须返回小写 `success`。

VPay 当前通知实现：

| 行为 | 说明 |
| --- | --- |
| 请求方式 | HTTP GET |
| 成功判断 | 响应正文 `trim()` 后必须等于 `success` |
| 超时 | 连接超时 5 秒，总超时 10 秒 |
| 重定向 | 不跟随重定向 |
| 协议 | 只允许 `http` 和 `https` |
| 内网地址 | 会拦截解析到内网或保留地址的通知 URL |
| HTTPS 校验 | 默认开启，可在后台配置中关闭通知 SSL 校验 |

如果 `notifyUrl` 为空，或后台默认异步回调地址为空，支付成功后异步通知会失败。生产环境请务必配置可公网访问的通知地址。

PHP 验签示例：

```php
<?php
$key = 'your_sign_key';

$payId = $_GET['payId'] ?? '';
$param = $_GET['param'] ?? '';
$type = $_GET['type'] ?? '';
$price = $_GET['price'] ?? '';
$reallyPrice = $_GET['reallyPrice'] ?? '';
$sign = $_GET['sign'] ?? '';

$expected = md5($payId . $param . $type . $price . $reallyPrice . $key);

if (!hash_equals($expected, $sign)) {
    http_response_code(400);
    echo 'fail';
    exit;
}

// 查询 payId 对应的商户订单，校验金额并幂等更新为已支付。

echo 'success';
```

## 9. 同步跳转

支付页检测到订单已支付后，会通过 `/checkOrder` 获取同步跳转 URL。同步跳转 URL 会在 `returnUrl` 后追加：

```text
payId, param, type, price, reallyPrice, sign
```

同步跳转使用与异步通知相同的验签规则。

当前代码中，同步跳转会将 `price` 和 `reallyPrice` 格式化为两位小数后再签名。商户同步页面验签时仍然应使用收到的字段原文。

如果 `returnUrl` 为空，`/checkOrder` 仍会返回由查询字符串组成的 URL。生产环境请配置有效同步跳转地址，避免用户支付完成后无法回到商户页面。

## 10. 自定义收银台说明

如果商户不使用 VPay 内置支付页，也可以自行展示二维码并轮询订单状态：

1. 调用 `/createOrder` 创建订单。
2. 展示返回的 `payUrl`。
3. 展示返回的 `reallyPrice`，并提醒用户按此金额付款。
4. 使用 `/getOrder` 查询订单信息。
5. 使用 `/checkOrder` 检测是否可跳转。

内置支付页路径：

```text
/payPage/pay.html?orderId=平台订单号
```

## 11. 代码对照清单

本节记录本文档与当前代码的核对结果。

| 文档事项 | 对照代码 | 核对结果 |
| --- | --- | --- |
| 商户接口路由为 `/createOrder`、`/getOrder`、`/checkOrder`、`/closeOrder` | `route/merchant.php` | 一致，均为 `Route::any` |
| 统一响应格式为 `code`、`msg`、`data` | `app/controller/trait/ApiResponse.php` | 一致 |
| 创建订单必填 `payId`、`type`、`price`、`sign` | `app/validate/OrderValidate.php` | 一致 |
| `payId` 最大 100 字符 | `app/validate/OrderValidate.php` | 一致 |
| `type` 只允许 `1`、`2` | `app/validate/OrderValidate.php`、`app/model/PayOrder.php` | 一致 |
| `price` 必须为 float、大于 0、小于 1000000 | `app/validate/OrderValidate.php` | 一致 |
| `notifyUrl`、`returnUrl` 最大 1000 字符 | `app/validate/OrderValidate.php` | 一致 |
| 下单签名为 `md5(payId + param + type + price + key)` | `app/service/SignService.php::verifyCreateOrderSign` | 一致 |
| 关闭订单签名为 `md5(orderId + key)` | `app/controller/merchant/Order.php::closeOrder`、`app/service/SignService.php::verifySimpleSign` | 一致 |
| 回调签名为 `md5(payId + param + type + price + reallyPrice + key)` | `app/service/SignService.php::makeOrderSign`、`buildNotifyQuery` | 一致 |
| 下单返回 `payId`、`orderId`、`payType`、`price`、`reallyPrice`、`payUrl`、`isAuto`、`state`、`timeOut`、`date` | `app/service/order/OrderPayloadFactory.php` | 一致 |
| 下单额外返回终端和通道快照字段 | `app/service/OrderCreationKernel.php::buildAndCacheOrderInfo` | 一致 |
| `reallyPrice` 由通道级金额占位服务生成，最多尝试 10 个金额 | `app/service/terminal/ChannelPriceReservationService.php` | 一致 |
| 商户订单号重复会返回 `商户订单号已存在` | `app/service/OrderCreationKernel.php::assertMerchantOrderNotExists` | 一致 |
| `getOrder` 不要求签名，按 `orderId` 查询 | `app/controller/merchant/Order.php::getOrder` | 一致 |
| `checkOrder` 未支付返回 `订单未支付`、过期返回 `订单已过期` | `app/controller/merchant/Order.php::checkOrder` | 一致 |
| `checkOrder` 对非 `0` 和非 `-1` 状态返回同步 URL | `app/controller/merchant/Order.php::checkOrder` | 一致，文档已注明此实现细节 |
| 异步通知使用 GET，请求成功必须返回 `success` | `app/service/NotifyService.php::sendNativeNotifyDetailed` | 一致 |
| 通知 URL 仅支持 HTTP/HTTPS，阻止内网地址，不跟随重定向 | `app/service/NotifyService.php::httpGetDetailed` | 一致 |
| 同步跳转金额格式化为两位小数，异步通知使用原始订单值 | `app/service/NotifyService.php::buildReturnUrl`、`app/service/SignService.php::buildNotifyQuery` | 一致，文档已注明 |

核对结论：本文档内容与当前商户相关代码一致。需要特别关注的实现细节是 `/checkOrder` 的状态判断较宽，商户侧应以异步通知为最终入账依据。
