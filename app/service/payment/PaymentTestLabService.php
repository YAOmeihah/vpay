<?php
declare(strict_types=1);

namespace app\service\payment;

use app\model\PayOrder;
use app\service\CacheService;
use app\service\NotifyService;
use app\service\SignService;
use think\facade\Cache;

class PaymentTestLabService
{
    private const CALLBACK_PREFIX = 'payment-test-lab:callback:';
    private const CALLBACK_TOKEN_PREFIX = 'payment-test-lab:callback-token:';
    private const CALLBACK_TTL = 7200;

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function createOrder(array $input, string $baseUrl): array
    {
        $type = (int)($input['type'] ?? 0);
        if (!in_array($type, [PayOrder::TYPE_WECHAT, PayOrder::TYPE_ALIPAY], true)) {
            throw new \RuntimeException('请选择有效的支付类型');
        }

        $price = $this->normalizePrice($input['price'] ?? '');
        $payId = trim((string)($input['payId'] ?? ''));
        if ($payId === '') {
            $payId = 'TEST-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        $param = trim((string)($input['param'] ?? ''));
        if ($param === '') {
            $param = 'VPay Payment Lab';
        }

        $normalizedBase = $this->normalizeBaseUrl($baseUrl);
        $callbackToken = $this->generateCallbackToken();
        $notifyUrl = trim((string)($input['notifyUrl'] ?? ''));
        if ($notifyUrl === '') {
            $notifyUrl = $this->internalCallbackUrl($normalizedBase, 'notify', $callbackToken);
        }

        $returnUrl = trim((string)($input['returnUrl'] ?? ''));
        if ($returnUrl === '') {
            $returnUrl = $this->internalCallbackUrl($normalizedBase, 'return', $callbackToken);
        }

        $signType = $this->normalizeSignType($input['signType'] ?? '');
        $request = [
            'payId' => $payId,
            'type' => $type,
            'price' => $price,
            'param' => $param,
            'notifyUrl' => $notifyUrl,
            'returnUrl' => $returnUrl,
            'signType' => $signType,
        ];
        $request['sign'] = SignService::makeCreateOrderSign(
            $payId,
            $param,
            $type,
            $price,
            $signType
        );

        $merchantResponse = $this->postCreateOrder($normalizedBase . '/createOrder', $request);
        $order = $this->extractCreatedOrder($merchantResponse);
        $record = PayOrder::where('order_id', $order['orderId'])->find();
        if (!$record) {
            throw new \RuntimeException('测试订单创建后未找到订单记录');
        }

        $this->cacheCallbackToken($payId, $callbackToken);
        $this->cacheCallbackToken((string)$order['orderId'], $callbackToken);

        return [
            'request' => $request,
            'order' => $this->formatOrderPayload($order, $record),
            'assignment' => $this->formatAssignment($record),
            'payPageUrl' => $normalizedBase . '/payPage/pay.html?orderId=' . rawurlencode((string) $order['orderId']),
            'callback' => $this->getLatestCallback((string)$order['orderId'], $payId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrderStatus(string $orderId): array
    {
        $normalizedOrderId = trim($orderId);
        if ($normalizedOrderId === '') {
            throw new \RuntimeException('订单号不能为空');
        }

        $record = PayOrder::where('order_id', $normalizedOrderId)->find();
        if (!$record) {
            throw new \RuntimeException('测试订单不存在');
        }

        $cached = CacheService::getOrder($normalizedOrderId) ?? [];
        $payload = [
            'payId' => (string)$record['pay_id'],
            'orderId' => (string)$record['order_id'],
            'payType' => (int)$record['type'],
            'price' => number_format((float)$record['price'], 2, '.', ''),
            'reallyPrice' => number_format((float)$record['really_price'], 2, '.', ''),
            'payUrl' => (string)$record['pay_url'],
            'isAuto' => (int)$record['is_auto'],
            'state' => (int)$record['state'],
            'timeOut' => $cached['timeOut'] ?? '',
            'date' => (int)$record['create_date'],
        ];

        $returnUrl = '';
        if ((int)$record['state'] > PayOrder::STATE_UNPAID && (string)$record['return_url'] !== '') {
            $returnUrl = NotifyService::buildReturnUrl($record->toArray());
        }

        return [
            'order' => $this->formatOrderPayload($payload, $record),
            'assignment' => $this->formatAssignment($record),
            'returnUrl' => $returnUrl,
            'callback' => $this->getLatestCallback((string)$record['order_id'], (string)$record['pay_id']),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function recordCallback(string $kind, array $payload, string $ip = ''): array
    {
        $normalizedPayload = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalizedPayload[(string)$key] = (string)$value;
            }
        }

        $payId = trim((string)($normalizedPayload['payId'] ?? ''));
        $record = $payId !== '' ? PayOrder::where('pay_id', $payId)->find() : null;
        $orderId = $record ? (string)$record['order_id'] : trim((string)($normalizedPayload['orderId'] ?? ''));
        $this->assertValidCallbackToken($normalizedPayload, $payId, $orderId);
        $signatureValid = $this->verifyCallbackSignature($normalizedPayload);

        $callback = [
            'kind' => $kind,
            'payId' => $payId,
            'orderId' => $orderId,
            'payload' => $normalizedPayload,
            'ip' => $ip,
            'receivedAt' => time(),
            'signatureValid' => $signatureValid,
        ];

        if ($payId !== '') {
            Cache::set($this->callbackKey($payId), $callback, self::CALLBACK_TTL);
        }
        if ($orderId !== '') {
            Cache::set($this->callbackKey($orderId), $callback, self::CALLBACK_TTL);
        }

        return $callback;
    }

    /**
     * @param array<string, string> $payload
     */
    private function verifyCallbackSignature(array $payload): bool
    {
        $sign = trim((string)($payload['sign'] ?? ''));
        $signType = (string)($payload['signType'] ?? '');
        if ($sign === '' || SignService::normalizeRequestedSignType($signType) === null) {
            return false;
        }

        foreach (['payId', 'type', 'price', 'reallyPrice'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $payload)) {
                return false;
            }
        }

        $expected = SignService::makeOrderSign(
            (string)$payload['payId'],
            (string)($payload['param'] ?? ''),
            (int)$payload['type'],
            (string)$payload['price'],
            (string)$payload['reallyPrice'],
            $signType
        );

        return hash_equals($expected, $sign);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatestCallback(string $orderId = '', string $payId = ''): ?array
    {
        foreach ([trim($orderId), trim($payId)] as $key) {
            if ($key === '') {
                continue;
            }

            $callback = Cache::get($this->callbackKey($key));
            if (is_array($callback)) {
                return $callback;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function formatOrderPayload(array $order, mixed $record): array
    {
        $state = $record ? (int)$record['state'] : (int)($order['state'] ?? PayOrder::STATE_UNPAID);
        $order['state'] = $state;
        $order['stateText'] = $this->stateText($state);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAssignment(mixed $record): array
    {
        if (!$record) {
            return [
                'terminalId' => 0,
                'channelId' => 0,
                'terminalName' => '',
                'channelName' => '',
                'assignStatus' => '',
                'assignReason' => '',
            ];
        }

        return [
            'terminalId' => (int)$record['terminal_id'],
            'channelId' => (int)$record['channel_id'],
            'terminalName' => (string)$record['terminal_snapshot'],
            'channelName' => (string)$record['channel_snapshot'],
            'assignStatus' => (string)$record['assign_status'],
            'assignReason' => (string)$record['assign_reason'],
        ];
    }

    private function normalizeSignType(mixed $value): string
    {
        $signType = SignService::normalizeRequestedSignType((string)$value);
        if ($signType === null) {
            throw new \RuntimeException('签名算法不支持');
        }

        return $signType;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function extractCreatedOrder(array $response): array
    {
        if ((int)($response['code'] ?? -1) !== 1) {
            $message = trim((string)($response['msg'] ?? ''));
            throw new \RuntimeException($message !== '' ? $message : '商户下单失败');
        }

        $data = $response['data'] ?? null;
        if (!is_array($data) || trim((string)($data['orderId'] ?? '')) === '') {
            throw new \RuntimeException('商户下单接口返回格式错误');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    protected function postCreateOrder(string $url, array $request): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL 扩展不可用，无法调用商户下单接口');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('调用商户下单接口失败');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($request),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $body = curl_exec($curl);
        $error = trim(curl_error($curl));
        $httpCode = (int)(curl_getinfo($curl, CURLINFO_RESPONSE_CODE) ?: 0);
        curl_close($curl);

        if (!is_string($body)) {
            throw new \RuntimeException($error !== '' ? '调用商户下单接口失败: ' . $error : '调用商户下单接口失败');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException('调用商户下单接口失败: HTTP ' . $httpCode);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('商户下单接口返回格式错误');
        }

        return $decoded;
    }

    private function normalizePrice(mixed $value): string
    {
        $price = (float)$value;
        if ($price <= 0) {
            throw new \RuntimeException('测试金额必须大于 0');
        }

        return number_format($price, 2, '.', '');
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $normalized = trim($baseUrl);
        if ($normalized === '') {
            throw new \RuntimeException('站点地址不能为空');
        }

        return rtrim($normalized, '/');
    }

    private function stateText(int $state): string
    {
        return match ($state) {
            PayOrder::STATE_UNPAID => '未支付',
            PayOrder::STATE_PAID => '已支付',
            PayOrder::STATE_NOTIFY_FAILED => '通知失败',
            PayOrder::STATE_EXPIRED => '已过期',
            PayOrder::STATE_CANCELLED => '已取消',
            PayOrder::STATE_ASSIGN_FAILED => '分配失败',
            default => '未知状态',
        };
    }

    private function callbackKey(string $value): string
    {
        return self::CALLBACK_PREFIX . sha1($value);
    }

    private function callbackTokenKey(string $value): string
    {
        return self::CALLBACK_TOKEN_PREFIX . sha1($value);
    }

    private function generateCallbackToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function internalCallbackUrl(string $baseUrl, string $kind, string $token): string
    {
        return $baseUrl . '/payment-test/' . $kind
            . '?vpayPaymentLab=1&token=' . rawurlencode($token);
    }

    private function cacheCallbackToken(string $key, string $token): void
    {
        $normalizedKey = trim($key);
        if ($normalizedKey === '') {
            return;
        }

        Cache::set($this->callbackTokenKey($normalizedKey), $token, self::CALLBACK_TTL);
    }

    /**
     * @param array<string, string> $payload
     */
    private function assertValidCallbackToken(array $payload, string $payId, string $orderId): void
    {
        $providedToken = trim((string)($payload['token'] ?? ''));
        if ($providedToken === '') {
            throw new \RuntimeException('测试回调令牌无效');
        }

        foreach ([trim($payId), trim($orderId)] as $key) {
            if ($key === '') {
                continue;
            }

            $expectedToken = (string)Cache::get($this->callbackTokenKey($key), '');
            if ($expectedToken !== '' && hash_equals($expectedToken, $providedToken)) {
                return;
            }
        }

        throw new \RuntimeException('测试回调令牌无效');
    }
}
