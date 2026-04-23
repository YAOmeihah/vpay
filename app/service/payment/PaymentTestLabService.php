<?php
declare(strict_types=1);

namespace app\service\payment;

use app\model\PayOrder;
use app\service\CacheService;
use app\service\NotifyService;
use app\service\OrderService;
use think\facade\Cache;

class PaymentTestLabService
{
    private const CALLBACK_PREFIX = 'payment-test-lab:callback:';
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
        $notifyUrl = trim((string)($input['notifyUrl'] ?? ''));
        if ($notifyUrl === '') {
            $notifyUrl = $normalizedBase . '/payment-test/notify?vpayPaymentLab=1';
        }

        $returnUrl = trim((string)($input['returnUrl'] ?? ''));
        if ($returnUrl === '') {
            $returnUrl = $normalizedBase . '/payment-test/return?vpayPaymentLab=1';
        }

        $request = [
            'payId' => $payId,
            'type' => $type,
            'price' => $price,
            'param' => $param,
            'notifyUrl' => $notifyUrl,
            'returnUrl' => $returnUrl,
        ];

        $order = OrderService::createOrder($request);
        $record = PayOrder::where('order_id', $order['orderId'])->find();

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

        $callback = [
            'kind' => $kind,
            'payId' => $payId,
            'orderId' => $orderId,
            'payload' => $normalizedPayload,
            'ip' => $ip,
            'receivedAt' => time(),
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
}
