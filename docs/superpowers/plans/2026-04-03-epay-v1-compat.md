# EPay v1 Single-Merchant Compatibility Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an EPay v1 single-merchant compatibility layer to this ThinkPHP 8 payment app so external merchants can create orders through `/mapi.php` or `/submit.php`, verify MD5 signatures, and receive standard EPay async notifications after payment success.

**Architecture:** Keep the existing payment/order lifecycle intact and add a thin EPay protocol adapter around it. Register dedicated EPay routes in `route/app.php`, implement a dedicated `app/controller/Epay.php` for protocol I/O, and move signing, config lookup, order mapping, and notify payload assembly into focused services under `app/service/epay/`. Reuse `Setting` for global single-merchant config and reuse `PayOrder` fields for merchant order number, passthrough param, notify URL, and return URL.

**Tech Stack:** PHP 8+, ThinkPHP 8, Think ORM, existing `Setting` and `PayOrder` models, existing payment flow in `app/controller/Index.php`, MD5 hashing.

---

## File Structure

**Create**
- `app/controller/Epay.php` — handles `/mapi.php` and `/submit.php`, validates request shape at the controller boundary, delegates to services, returns EPay-compatible responses.
- `app/service/epay/EpayConfigService.php` — reads and normalizes single-merchant EPay configuration from `Setting`.
- `app/service/epay/EpaySignService.php` — builds sign content, creates MD5 signatures, verifies incoming signatures.
- `app/service/epay/EpayOrderService.php` — validates create-order requests, maps EPay fields to the local order flow, returns normalized result data.
- `app/service/epay/EpayNotifyService.php` — builds merchant notify payloads in EPay format and posts them to the merchant `notify_url`.
- `app/service/epay/EpayResponseService.php` — formats success/failure payloads for `/mapi.php` and redirect metadata for `/submit.php`.

**Modify**
- `route/app.php` — add `/mapi.php` and `/submit.php` routes.
- `app/controller/Admin.php:218-281` — expose and save EPay config keys through the existing settings APIs.
- `app/model/Setting.php:31-74` — no schema change needed, but this file is part of the configuration access path referenced by the plan.
- `app/controller/Index.php:192` (`createOrder`) — read only during implementation to reuse field mapping and order creation behavior.
- `app/controller/Index.php:585` (`appPush`) — read only during implementation to hook or mirror existing merchant notification success/failure handling.
- `app/model/PayOrder.php:20-36` — confirm existing fields are sufficient for `out_trade_no`, `param`, `notify_url`, and `return_url` without adding columns in phase 1.

**Tests**
- No verified repository test harness exists yet. This plan uses focused manual verification commands with `php think run` and `curl`. If a PHP test framework is introduced later, port the manual cases into automated tests.

---

### Task 1: Expose EPay configuration in the existing settings API

**Files:**
- Modify: `app/controller/Admin.php:218-281`
- Modify: `app/model/Setting.php:31-74`
- Create: `app/service/epay/EpayConfigService.php`

- [ ] **Step 1: Create the config reader service**

```php
<?php
declare(strict_types=1);

namespace app\service\epay;

use app\model\Setting;

class EpayConfigService
{
    public static function getConfig(): array
    {
        return [
            'enabled' => Setting::getConfigValue('epay_enabled', '0') === '1',
            'pid' => trim(Setting::getConfigValue('epay_pid')),
            'key' => trim(Setting::getConfigValue('epay_key')),
            'name' => trim(Setting::getConfigValue('epay_name', '订单支付')),
        ];
    }

    public static function requireEnabledConfig(): array
    {
        $config = static::getConfig();

        if (!$config['enabled']) {
            throw new \RuntimeException('EPay 通道未启用');
        }

        if ($config['pid'] === '' || $config['key'] === '') {
            throw new \RuntimeException('EPay 配置不完整');
        }

        return $config;
    }
}
```

- [ ] **Step 2: Add EPay keys to `getSettings()`**

Add these entries to the `$settings` array in `app/controller/Admin.php:224-237`:

```php
'epay_enabled' => Setting::getConfigValue('epay_enabled', '0'),
'epay_pid' => Setting::getConfigValue('epay_pid'),
'epay_key' => Setting::getConfigValue('epay_key'),
'epay_name' => Setting::getConfigValue('epay_name', '订单支付'),
```

- [ ] **Step 3: Add EPay keys to `saveSetting()`**

Extend the `$params` array in `app/controller/Admin.php:257-260` to:

```php
$params = [
    'user', 'pass', 'notifyUrl', 'returnUrl', 'key',
    'close', 'payQf', 'wxpay', 'zfbpay',
    'epay_enabled', 'epay_pid', 'epay_key', 'epay_name',
];
```

- [ ] **Step 4: Run a quick PHP syntax check**

Run: `php -l app/controller/Admin.php && php -l app/service/epay/EpayConfigService.php`
Expected: `No syntax errors detected` for both files.

- [ ] **Step 5: Verify settings API manually**

Run the app: `php think run`

Then request the admin settings API after logging in:

```bash
curl "http://127.0.0.1:8000/admin/index/getSettings"
```

Expected: the JSON payload contains `epay_enabled`, `epay_pid`, `epay_key`, and `epay_name` keys.

- [ ] **Step 6: Commit**

```bash
git add app/controller/Admin.php app/service/epay/EpayConfigService.php
git commit -m "feat: add epay configuration access"
```

### Task 2: Implement deterministic EPay MD5 signing

**Files:**
- Create: `app/service/epay/EpaySignService.php`

- [ ] **Step 1: Write the signing service**

```php
<?php
declare(strict_types=1);

namespace app\service\epay;

class EpaySignService
{
    public static function buildSignContent(array $params): string
    {
        $filtered = [];

        foreach ($params as $key => $value) {
            if ($key === 'sign' || $key === 'sign_type') {
                continue;
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $filtered[(string)$key] = (string)$value;
        }

        ksort($filtered);

        $pairs = [];
        foreach ($filtered as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }

        return implode('&', $pairs);
    }

    public static function makeMd5(array $params, string $key): string
    {
        return strtolower(md5(static::buildSignContent($params) . $key));
    }

    public static function verifyMd5(array $params, string $key): bool
    {
        $inputSign = strtolower((string)($params['sign'] ?? ''));
        if ($inputSign === '') {
            return false;
        }

        return hash_equals(static::makeMd5($params, $key), $inputSign);
    }
}
```

- [ ] **Step 2: Run a syntax check**

Run: `php -l app/service/epay/EpaySignService.php`
Expected: `No syntax errors detected in app/service/epay/EpaySignService.php`

- [ ] **Step 3: Verify the sign-content algorithm with a one-off CLI snippet**

Run:

```bash
php -r "require 'vendor/autoload.php'; require 'app/service/epay/EpaySignService.php'; echo app\\service\\epay\\EpaySignService::buildSignContent(['pid'=>'1001','type'=>'alipay','money'=>'10.00','sign'=>'x','sign_type'=>'MD5','param'=>'9527']);"
```

Expected output:

```text
money=10.00&param=9527&pid=1001&type=alipay
```

- [ ] **Step 4: Commit**

```bash
git add app/service/epay/EpaySignService.php
git commit -m "feat: add epay md5 signing service"
```

### Task 3: Add response formatting for EPay v1

**Files:**
- Create: `app/service/epay/EpayResponseService.php`

- [ ] **Step 1: Create the response formatter**

```php
<?php
declare(strict_types=1);

namespace app\service\epay;

class EpayResponseService
{
    public static function success(array $payload): array
    {
        return [
            'code' => 1,
            'msg' => 'success',
            'trade_no' => $payload['trade_no'],
            'payurl' => $payload['payurl'] ?? '',
            'qrcode' => $payload['qrcode'] ?? '',
            'urlscheme' => $payload['urlscheme'] ?? '',
        ];
    }

    public static function fail(string $message, int $code = -1): array
    {
        return [
            'code' => $code,
            'msg' => $message,
        ];
    }
}
```

- [ ] **Step 2: Run a syntax check**

Run: `php -l app/service/epay/EpayResponseService.php`
Expected: `No syntax errors detected in app/service/epay/EpayResponseService.php`

- [ ] **Step 3: Commit**

```bash
git add app/service/epay/EpayResponseService.php
git commit -m "feat: add epay response formatter"
```

### Task 4: Build the EPay order adapter service

**Files:**
- Create: `app/service/epay/EpayOrderService.php`
- Modify: `app/model/PayOrder.php:20-36`
- Read during implementation: `app/controller/Index.php:192-584`

- [ ] **Step 1: Create the type mapper and request validator**

```php
private static function mapType(string $type): int
{
    return match ($type) {
        'wxpay' => \app\model\PayOrder::TYPE_WECHAT,
        'alipay' => \app\model\PayOrder::TYPE_ALIPAY,
        default => 0,
    };
}

private static function validateRequest(array $params, array $config): void
{
    if (($params['pid'] ?? '') !== $config['pid']) {
        throw new \RuntimeException('pid错误');
    }

    if (!in_array(($params['type'] ?? ''), ['wxpay', 'alipay'], true)) {
        throw new \RuntimeException('暂不支持该支付类型');
    }

    if (!isset($params['out_trade_no']) || trim((string)$params['out_trade_no']) === '') {
        throw new \RuntimeException('商户订单号不能为空');
    }

    if (!isset($params['money']) || !is_numeric((string)$params['money'])) {
        throw new \RuntimeException('金额格式错误');
    }

    if (!EpaySignService::verifyMd5($params, $config['key'])) {
        throw new \RuntimeException('签名校验失败');
    }
}
```

- [ ] **Step 2: Create the first version of `EpayOrderService`**

```php
<?php
declare(strict_types=1);

namespace app\service\epay;

use app\model\PayOrder;

class EpayOrderService
{
    public static function create(array $params): array
    {
        $config = EpayConfigService::requireEnabledConfig();
        static::validateRequest($params, $config);

        $type = static::mapType((string)$params['type']);
        $price = round((float)$params['money'], 2);
        $orderId = date('YmdHis') . mt_rand(100000, 999999);

        $order = PayOrder::create([
            'order_id' => $orderId,
            'pay_id' => (string)$params['out_trade_no'],
            'param' => (string)($params['param'] ?? ''),
            'notify_url' => (string)$params['notify_url'],
            'return_url' => (string)$params['return_url'],
            'price' => $price,
            'really_price' => $price,
            'type' => $type,
            'state' => PayOrder::STATE_UNPAID,
            'pay_url' => '',
            'is_auto' => 0,
            'create_date' => time(),
            'close_date' => time() + 300,
            'pay_date' => 0,
        ]);

        return [
            'trade_no' => (string)$order['order_id'],
            'payurl' => (string)$order['pay_url'],
            'qrcode' => (string)$order['pay_url'],
            'urlscheme' => '',
        ];
    }

    private static function mapType(string $type): int
    {
        return match ($type) {
            'wxpay' => PayOrder::TYPE_WECHAT,
            'alipay' => PayOrder::TYPE_ALIPAY,
            default => 0,
        };
    }

    private static function validateRequest(array $params, array $config): void
    {
        if (($params['pid'] ?? '') !== $config['pid']) {
            throw new \RuntimeException('pid错误');
        }

        if (!in_array(($params['type'] ?? ''), ['wxpay', 'alipay'], true)) {
            throw new \RuntimeException('暂不支持该支付类型');
        }

        if (!isset($params['out_trade_no']) || trim((string)$params['out_trade_no']) === '') {
            throw new \RuntimeException('商户订单号不能为空');
        }

        if (!isset($params['money']) || !is_numeric((string)$params['money'])) {
            throw new \RuntimeException('金额格式错误');
        }

        if (!isset($params['notify_url']) || trim((string)$params['notify_url']) === '') {
            throw new \RuntimeException('异步通知地址不能为空');
        }

        if (!isset($params['return_url']) || trim((string)$params['return_url']) === '') {
            throw new \RuntimeException('同步跳转地址不能为空');
        }

        if (!EpaySignService::verifyMd5($params, $config['key'])) {
            throw new \RuntimeException('签名校验失败');
        }
    }
}
```

- [ ] **Step 3: Replace direct `PayOrder::create(...)` with the proven local order creation flow**

Read `app/controller/Index.php:192-584` and replace the provisional `PayOrder::create([...])` block with the same field derivation pattern used by `createOrder()`. Keep the EPay mapping rules below exactly aligned:

```php
[
    'pay_id' => (string)$params['out_trade_no'],
    'param' => (string)($params['param'] ?? ''),
    'notify_url' => (string)$params['notify_url'],
    'return_url' => (string)$params['return_url'],
    'type' => static::mapType((string)$params['type']),
]
```

Success condition: `pay_url` or QR content is populated from the same source the native order flow uses, rather than leaving `pay_url` blank.

- [ ] **Step 4: Run syntax checks**

Run: `php -l app/service/epay/EpayOrderService.php && php -l app/model/PayOrder.php`
Expected: `No syntax errors detected` for both files.

- [ ] **Step 5: Verify rejected requests manually**

Run the app: `php think run`

Then send a bad-signature request:

```bash
curl -X POST "http://127.0.0.1:8000/mapi.php" -d "pid=bad&type=alipay&out_trade_no=ORDER001&notify_url=https://merchant.test/notify&return_url=https://merchant.test/return&name=测试订单&money=10.00&param=9527&sign=bad&sign_type=MD5"
```

Expected JSON:

```json
{"code":-1,"msg":"签名校验失败"}
```

- [ ] **Step 6: Commit**

```bash
git add app/service/epay/EpayOrderService.php app/model/PayOrder.php
git commit -m "feat: add epay order adapter"
```

### Task 5: Add the dedicated EPay controller and routes

**Files:**
- Create: `app/controller/Epay.php`
- Modify: `route/app.php:11-47`

- [ ] **Step 1: Create the controller**

```php
<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\epay\EpayOrderService;
use app\service\epay\EpayResponseService;

class Epay extends BaseController
{
    public function mapi()
    {
        try {
            $payload = EpayOrderService::create($this->request->param());
            return json(EpayResponseService::success($payload));
        } catch (\Throwable $e) {
            return json(EpayResponseService::fail($e->getMessage()));
        }
    }

    public function submit()
    {
        try {
            $payload = EpayOrderService::create($this->request->param());
            if (!empty($payload['payurl'])) {
                return redirect($payload['payurl']);
            }

            return json(EpayResponseService::success($payload));
        } catch (\Throwable $e) {
            return json(EpayResponseService::fail($e->getMessage()));
        }
    }
}
```

- [ ] **Step 2: Register the routes**

Add these lines near the existing public routes in `route/app.php`:

```php
Route::post('mapi.php', 'epay/mapi');
Route::get('submit.php', 'epay/submit');
```

- [ ] **Step 3: Run syntax checks**

Run: `php -l app/controller/Epay.php && php -l route/app.php`
Expected: `No syntax errors detected` for both files.

- [ ] **Step 4: Verify route registration**

Run: `php think route:list`
Expected: the route table includes `/mapi.php` and `/submit.php` mapped to the `epay` controller.

- [ ] **Step 5: Commit**

```bash
git add app/controller/Epay.php route/app.php
git commit -m "feat: add epay gateway routes"
```

### Task 6: Send EPay-format async notifications after successful payment

**Files:**
- Create: `app/service/epay/EpayNotifyService.php`
- Read during implementation: `app/controller/Index.php:585-760`

- [ ] **Step 1: Create the notify payload builder**

```php
<?php
declare(strict_types=1);

namespace app\service\epay;

use app\model\PayOrder;

class EpayNotifyService
{
    public static function buildPayload(PayOrder $order): array
    {
        $config = EpayConfigService::requireEnabledConfig();

        $payload = [
            'pid' => $config['pid'],
            'out_trade_no' => (string)$order->pay_id,
            'trade_no' => (string)$order->order_id,
            'trade_status' => 'TRADE_SUCCESS',
            'money' => number_format((float)$order->price, 2, '.', ''),
            'param' => (string)$order->param,
            'sign_type' => 'MD5',
        ];

        $payload['sign'] = EpaySignService::makeMd5($payload, $config['key']);

        return $payload;
    }
}
```

- [ ] **Step 2: Add the sender method**

Extend `EpayNotifyService` with:

```php
public static function send(PayOrder $order): string
{
    $payload = static::buildPayload($order);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, (string)$order->notify_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = (string)curl_exec($ch);
    curl_close($ch);

    return trim($response);
}
```

- [ ] **Step 3: Replace the existing merchant notify payload assembly in `Index::appPush()`**

Read `app/controller/Index.php:585-760` and find the block that posts to the merchant `notify_url`. Replace its payload construction with:

```php
$response = \app\service\epay\EpayNotifyService::send($order);
if ($response === 'success') {
    $order->state = \app\model\PayOrder::STATE_PAID;
} else {
    $order->state = \app\model\PayOrder::STATE_NOTIFY_FAILED;
}
$order->save();
```

Keep any existing success timestamp updates and duplicate-order guards already present in `appPush()`.

- [ ] **Step 4: Run syntax checks**

Run: `php -l app/service/epay/EpayNotifyService.php && php -l app/controller/Index.php`
Expected: `No syntax errors detected` for both files.

- [ ] **Step 5: Manual notification verification**

After a successful payment in the local app, inspect the merchant endpoint logs and confirm the posted form body contains:

```text
pid=...&out_trade_no=...&trade_no=...&trade_status=TRADE_SUCCESS&money=10.00&param=...&sign=...&sign_type=MD5
```

Expected merchant response handling:
- response body `success` → local order remains paid
- response body not equal to `success` → local order state becomes notify failed

- [ ] **Step 6: Commit**

```bash
git add app/service/epay/EpayNotifyService.php app/controller/Index.php
git commit -m "feat: add epay async notification format"
```

### Task 7: End-to-end manual verification of the compatibility layer

**Files:**
- Modify: none
- Test: local running app and HTTP requests

- [ ] **Step 1: Start the local server**

Run: `php think run --host 127.0.0.1 --port 8000`
Expected: ThinkPHP development server starts successfully.

- [ ] **Step 2: Generate a valid MD5 signature for a sample order**

Run:

```bash
php -r "require 'vendor/autoload.php'; require 'app/service/epay/EpaySignService.php'; echo app\\service\\epay\\EpaySignService::makeMd5(['pid'=>'YOUR_PID','type'=>'alipay','out_trade_no'=>'ORDER123','notify_url'=>'https://merchant.test/notify','return_url'=>'https://merchant.test/return','name'=>'测试订单','money'=>'10.00','param'=>'9527','sign_type'=>'MD5'], 'YOUR_KEY');"
```

Expected: a 32-character lowercase MD5 string.

- [ ] **Step 3: Submit a valid `/mapi.php` request**

Run:

```bash
curl -X POST "http://127.0.0.1:8000/mapi.php" -d "pid=YOUR_PID&type=alipay&out_trade_no=ORDER123&notify_url=https://merchant.test/notify&return_url=https://merchant.test/return&name=测试订单&money=10.00&param=9527&sign=GENERATED_SIGN&sign_type=MD5"
```

Expected JSON contains:

```json
{
  "code": 1,
  "msg": "success",
  "trade_no": "..."
}
```

and at least one of `payurl` or `qrcode` is non-empty.

- [ ] **Step 4: Submit a valid `/submit.php` request in a browser**

Open:

```text
http://127.0.0.1:8000/submit.php?pid=YOUR_PID&type=alipay&out_trade_no=ORDER124&notify_url=https://merchant.test/notify&return_url=https://merchant.test/return&name=测试订单&money=10.00&param=9528&sign=GENERATED_SIGN&sign_type=MD5
```

Expected: the browser redirects to the payment URL when `payurl` exists, otherwise returns the same JSON structure as `/mapi.php`.

- [ ] **Step 5: Verify unsupported-type rejection**

Run:

```bash
curl -X POST "http://127.0.0.1:8000/mapi.php" -d "pid=YOUR_PID&type=qqpay&out_trade_no=ORDER125&notify_url=https://merchant.test/notify&return_url=https://merchant.test/return&name=测试订单&money=10.00&param=9529&sign=bad&sign_type=MD5"
```

Expected JSON:

```json
{"code":-1,"msg":"暂不支持该支付类型"}
```

- [ ] **Step 6: Commit**

```bash
git commit --allow-empty -m "test: verify epay v1 compatibility manually"
```

---

## Self-review

- Spec coverage: this plan covers single merchant only, `Setting`-based config, EPay v1 paths `/mapi.php` and `/submit.php`, MD5 sign/verify, `wxpay` and `alipay`, async notify payload formatting, and the `success` response-body rule.
- Placeholder scan: removed `TBD`/`TODO` language and replaced it with file-specific tasks, code blocks, commands, and expected outcomes.
- Type consistency: the plan consistently uses `epay_enabled`, `epay_pid`, `epay_key`, `epay_name`, `EpayConfigService`, `EpaySignService`, `EpayOrderService`, `EpayNotifyService`, and `EpayResponseService`.
