# 当前项目易支付兼容接入文档

本文档用于指导第三方支付项目实现一个“易支付兼容层”，以便对接当前项目（dujiao-next）中已经实现的易支付通道。

> 结论优先：本文档基于当前项目现有代码实现整理。你的支付项目若按本文档中的字段名、签名规则、返回格式、异步通知格式实现，可与当前项目现有易支付对接逻辑保持一致。

---

## 1. 协议版本概览

当前项目支持两套易支付风格协议：

### 1.1 v1
- 下单接口路径：`/mapi.php`
- 跳转收银台路径：`/submit.php`
- 签名方式：`MD5`
- 典型交互：表单跳转 / 服务端 POST 创建订单

### 1.2 v2
- 下单接口路径：`/api/pay/create`
- 跳转收银台路径：`/api/pay/submit`
- 签名方式：`RSA`
- 典型交互：服务端 JSON / 表单创建订单 + 网页跳转

---

## 2. 下单接口规范

当前项目在创建支付时，会向你的“易支付兼容层”发起请求。

### 2.1 v1 下单

**请求方式**
- 服务端创建：`POST`
- 页面跳转：`GET` 或表单跳转均可，本质目标地址为 `/submit.php`

**接口地址**
- `POST {gateway_url}/mapi.php`
- `GET  {gateway_url}/submit.php`

**请求参数**

| 参数名 | 必填 | 说明 |
|---|---:|---|
| `pid` | 是 | 商户号 |
| `type` | 是 | 支付方式，支持 `wxpay` / `alipay` / `qqpay` |
| `out_trade_no` | 是 | 商户订单号 |
| `notify_url` | 是 | 异步通知地址 |
| `return_url` | 是 | 同步跳转地址（当前项目创建请求会传入；但支付状态确认核心依赖异步通知） |
| `name` | 是 | 订单标题 / 商品名称 |
| `money` | 是 | 支付金额，字符串形式，建议两位小数 |
| `clientip` | POST 时建议提供 | 用户 IP |
| `device` | POST 时建议提供 | 设备类型，当前项目默认 `pc` |
| `param` | 强烈建议支持 | 附加参数，当前项目会放入本地 `paymentID`，回调时必须原样返回 |
| `sign` | 是 | 签名值 |
| `sign_type` | 是 | 签名类型，v1 默认为 `MD5` |

### 2.2 v2 下单

**请求方式**
- 服务端创建：`POST`
- 页面跳转：`GET` 或表单跳转均可，本质目标地址为 `/api/pay/submit`

**接口地址**
- `POST {gateway_url}/api/pay/create`
- `GET  {gateway_url}/api/pay/submit`

**请求参数**

| 参数名 | 必填 | 说明 |
|---|---:|---|
| `pid` | 是 | 商户号 |
| `method` | POST 时建议提供 | 当前项目默认 `web` |
| `type` | 是 | 支付方式，支持 `wxpay` / `alipay` / `qqpay` |
| `out_trade_no` | 是 | 商户订单号 |
| `notify_url` | 是 | 异步通知地址 |
| `return_url` | 是 | 同步跳转地址（当前项目创建请求会传入；但支付状态确认核心依赖异步通知） |
| `name` | 是 | 订单标题 / 商品名称 |
| `money` | 是 | 支付金额，字符串形式，建议两位小数 |
| `clientip` | POST 时建议提供 | 用户 IP |
| `timestamp` | 是 | Unix 时间戳（秒） |
| `param` | 强烈建议支持 | 附加参数，当前项目用它携带本地 `paymentID` |
| `sign` | 是 | RSA 签名值 |
| `sign_type` | 是 | 签名类型，v2 默认为 `RSA` |

---

## 3. 金额与订单号要求

### 3.1 订单号字段
- 对外订单号字段固定为：`out_trade_no`
- 该字段应表示“商户订单号”
- 回调时必须原样返回该字段

### 3.2 金额字段
- 字段名固定为：`money`
- 类型应兼容字符串，例如：
  - `10`
  - `10.00`
  - `99.50`
- 建议统一保留两位小数
- 回调时金额必须与创建时一致，否则当前项目会判定金额不匹配并拒绝回调

---

## 4. 支付方式映射

当前项目内易支付支持以下 `type`：

| type | 含义 |
|---|---|
| `wxpay` | 微信支付 |
| `alipay` | 支付宝 |
| `qqpay` | QQ 支付 |

建议你的支付项目至少兼容：
- `wxpay`
- `alipay`

如果你的内部系统是：
- `1 = 微信`
- `2 = 支付宝`

可映射为：
- `wxpay -> 1`
- `alipay -> 2`
- `qqpay -> 可选扩展`

---

## 5. 签名规则

这是兼容的关键。

### 5.1 通用签名串规则
无论下单还是回调，当前项目采用同一类签名串构造方式：

1. 收集全部参数
2. 排除 `sign`
3. 排除空值参数
4. 按参数名 ASCII/字典序升序排序
5. 按 `key=value` 用 `&` 拼接

示例：

```text
money=10.00&name=VIP会员&notify_url=https://a.com/notify&out_trade_no=ORDER123&pid=1001&return_url=https://a.com/return&type=alipay
```

> 当前项目签名串构造时会排除 `sign` 和 `sign_type`，同时排除空值参数；随后按参数名 ASCII/字典序升序排序，并按 `key=value` 用 `&` 拼接。

### 5.2 v1 签名

**算法**
```text
sign = md5(signContent + merchant_key)
```

**要求**
- `merchant_key` 为商户密钥
- 输出大小写建议统一小写
- 当前项目校验时对大小写不敏感
- `sign_type` 固定/默认：`MD5`

### 5.3 v2 签名

**算法**
- 使用商户私钥对 `signContent` 做 `RSA` 签名
- 当前项目回调验签时使用平台公钥校验

**要求**
- `sign_type` 固定/默认：`RSA`
- `sign` 建议使用 Base64 编码输出
- 下单与回调均应遵守同一签名串构造规则

---

## 6. 下单返回格式规范

当前项目会解析你的下单返回结果。

### 6.1 v1 返回
兼容层建议返回 JSON，字段建议如下：

```json
{
  "code": 1,
  "msg": "success",
  "trade_no": "202604031234567890",
  "payurl": "https://example.com/pay/xxx",
  "qrcode": "weixin://wxpay/bizpayurl?pr=xxx",
  "urlscheme": "alipays://platformapi/startapp?xxx"
}
```

**字段说明**
- `code`：成功状态码，当前项目按 `code == 1` 判定 v1 下单成功
- `msg`：提示信息
- `trade_no`：平台订单号
- `payurl`：支付链接
- `qrcode`：二维码内容
- `urlscheme`：唤起链接（可选）

> 当前项目会读取这些字段中的支付地址类信息。

### 6.2 v2 返回
当前项目对 v2 的解析更明确：

```json
{
  "code": 0,
  "msg": "success",
  "trade_no": "202604031234567890",
  "pay_type": "url",
  "pay_info": "https://example.com/pay/xxx"
}
```

二维码模式示例：

```json
{
  "code": 0,
  "msg": "success",
  "trade_no": "202604031234567890",
  "pay_type": "qrcode",
  "pay_info": "weixin://wxpay/bizpayurl?pr=xxx"
}
```

**字段说明**
- `code = 0` 表示成功（当前项目按 `code == 0` 判定 v2 下单成功）
- `msg`：错误或成功信息
- `trade_no`：平台单号
- `pay_type`：支付信息类型
  - `qrcode`：表示 `pay_info` 是二维码内容
  - 其他值：表示 `pay_info` 是支付 URL / 拉起链接
- `pay_info`：实际支付信息

### 6.3 失败返回示例

```json
{
  "code": -1,
  "msg": "channel not available"
}
```

建议保证：
- `msg` 始终可读
- 失败时保留 `code != 0`（v2）或非成功码（v1）

---

## 7. 异步通知规范

当前项目非常依赖异步通知完成支付成功闭环。

### 7.1 通知方式
- 推荐：`POST`
- 表单方式最稳妥：`application/x-www-form-urlencoded`

### 7.2 通知参数
建议至少包含以下字段：

| 参数名 | 必填 | 说明 |
|---|---:|---|
| `pid` | 是 | 商户号，当前项目会校验必须与配置一致 |
| `out_trade_no` | 是 | 商户订单号 |
| `trade_no` | 建议 | 平台订单号 |
| `api_trade_no` | 可选 | 如果没有 `trade_no`，可用此字段兜底 |
| `trade_status` | 是 | 支付状态，成功必须为 `TRADE_SUCCESS` |
| `money` | 建议 | 支付金额，建议原样返回 |
| `param` | 是 | 创建支付时传入的附加参数，必须原样返回 |
| `sign` | 是 | 签名 |
| `sign_type` | 是 | 签名类型 |
| `endtime` | 可选 | 支付完成时间，Unix 时间戳 |
| `addtime` | 可选 | 创建时间，Unix 时间戳 |

### 7.3 成功状态值
当前项目要求：

```text
trade_status = TRADE_SUCCESS
```

否则会被映射为失败状态。

### 7.4 通知成功响应
这里需要区分“支付成功状态”和“通知响应体”：

- `trade_status=TRADE_SUCCESS` 表示通知请求中的支付成功状态
- 商户端（当前项目）在成功处理通知后返回的响应体是：

```text
success
```

处理失败时返回：

```text
fail
```

因此你的兼容层应当：
- 收到字面量 `success` 视为通知成功
- 收到非 `success` 视为失败并可重试

### 7.5 重试建议
当前项目代码未直接规定通知重试次数；以下内容属于支付网关通用实践建议，不属于本项目强制兼容要求：
- 回调失败时进行重试
- 至少重试 3~6 次
- 使用递增退避间隔

---

## 8. 回调校验规则（当前项目会做的事）

你的兼容层必须满足这些校验，否则回调会被拒收：

1. **签名必须正确**
2. **`pid` 必须与商户配置一致**
3. **`param` 必须存在且可解析**
   - 当前项目用它承载本地 `paymentID`
4. **`out_trade_no` 必须与原订单匹配**
5. **`money` 若传入，则必须与创建支付时金额一致**
6. **若订单已成功，再次回调不会回退状态（幂等）**

---

## 9. 平台单号 / 商户单号映射建议

你的支付系统如果内部同时存在：
- 商户订单号
- 平台订单号

建议映射如下：

| 语义 | 对外字段 |
|---|---|
| 商户订单号 | `out_trade_no` |
| 平台订单号 | `trade_no` |
| 商户号 | `pid` |
| 附加透传参数 | `param` |

**建议规则**
- 查单优先支持 `out_trade_no`
- 回调里同时返回 `out_trade_no` 和 `trade_no`
- `param` 必须原样透传

---

## 10. 页面跳转 / 收银台兼容

当前项目支持两种交互模式：

### 10.1 redirect 模式
- 当前项目会拼接参数后直接引导用户跳转到：
  - v1: `/submit.php`
  - v2: `/api/pay/submit`
- 因此你的兼容层应支持浏览器打开该地址并拉起支付页面

### 10.2 qr 模式
- 当前项目调用服务端接口创建支付
- 你返回二维码链接或支付 URL
- 当前项目将其展示给用户扫码/拉起

---

## 11. 查单接口说明

当前项目代码里，易支付对接核心闭环主要依赖：
- 创建支付
- 异步通知

未看到它强依赖一个标准易支付“查单接口”完成支付确认。

因此：
- **查单接口不是当前兼容的第一优先级**
- 下述内容仅属于扩展建议，并非当前项目代码已验证的兼容协议要求
- 如果你想补充，可额外提供一个兼容接口，例如：

### 建议查单接口（扩展建议，非当前项目已验证要求）
`GET /api/order/query`

参数：
- `pid`
- `out_trade_no` 或 `trade_no`
- `sign`
- `sign_type`

建议返回：

```json
{
  "code": 0,
  "msg": "success",
  "trade_no": "202604031234567890",
  "out_trade_no": "ORDER123",
  "status": "TRADE_SUCCESS",
  "money": "10.00"
}
```

但这部分属于**增强兼容**，不是当前项目接入的核心必需项。

---

## 12. 最低兼容实现清单

如果你只想最小成本兼容当前项目，请至少实现：

### 必做
1. v1 或 v2 下单接口（二选一，推荐先做 v1 MD5）
2. `wxpay` / `alipay` 两种 `type`
3. 标准签名校验
4. 异步通知
5. 异步通知成功返回识别 `success`
6. 原样透传 `param`

### 推荐补充
1. `qqpay`
2. redirect 页面
3. v2 RSA 支持
4. 查单接口

---

## 13. 建议的实现顺序

以下内容属于实施建议，不属于当前项目代码强制要求。

建议你的支付项目这样改：

### 方案 A：先做最小兼容
- 实现 `POST /mapi.php`
- 支持字段：`pid/type/out_trade_no/notify_url/return_url/name/money/param/sign/sign_type`
- 签名使用 MD5
- 成功后向 `notify_url` 回调
- 收到 `success` 即完成闭环

### 方案 B：再补 redirect
- 增加 `/submit.php`
- 支持浏览器跳转

### 方案 C：最后补 v2
- 增加 `/api/pay/create`
- 增加 `/api/pay/submit`
- 支持 RSA 签名

---

## 14. 你实现时最容易踩坑的点

1. **不要丢 `param`**
   - 当前项目依赖它定位内部支付记录
2. **`trade_status` 成功值必须是 `TRADE_SUCCESS`**
3. **`pid` 必须回传且正确**
4. **金额 `money` 要和原订单一致**
5. **回调响应必须识别 `success`，不能只看 HTTP 200**
6. **签名串构造必须严格一致**

---

## 15. 推荐返回与回调样例

### 下单请求（v1）
```http
POST /mapi.php
Content-Type: application/x-www-form-urlencoded

pid=1001&type=alipay&out_trade_no=ORDER123&notify_url=https://merchant.com/notify&return_url=https://merchant.com/return&name=VIP会员&money=10.00&param=9527&sign=xxxx&sign_type=MD5
```

### 下单成功响应（v2 风格）
```json
{
  "code": 0,
  "msg": "success",
  "trade_no": "202604031234567890",
  "pay_type": "url",
  "pay_info": "https://pay.example.com/cashier/abc"
}
```

### 异步通知请求
```http
POST /notify
Content-Type: application/x-www-form-urlencoded

pid=1001&out_trade_no=ORDER123&trade_no=202604031234567890&trade_status=TRADE_SUCCESS&money=10.00&param=9527&sign=xxxx&sign_type=MD5&endtime=1712100000
```

### 商户成功响应
```text
success
```

---

## 16. 最终结论

如果你的支付项目要兼容当前项目的易支付接入，最关键的是：

- 对外字段名应按当前项目现有易支付对接逻辑输出
- 必须支持 `out_trade_no / money / param / sign / sign_type`
- 必须支持异步通知，且支付成功状态为 `TRADE_SUCCESS`
- 必须把 `param` 原样回传
- 商户端成功处理通知后的响应体是 `success`
- 必须保证签名规则与当前项目一致（签名串排除 `sign`、`sign_type` 与空值参数）
- `pid`、订单号、金额需能通过当前项目校验

只要这些条件满足，你的支付系统即可与当前项目现有易支付网关对接逻辑保持一致。
