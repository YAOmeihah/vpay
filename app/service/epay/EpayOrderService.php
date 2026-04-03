<?php
declare(strict_types=1);

namespace app\service\epay;

use app\model\PayOrder;
use app\service\OrderCreationKernel;
use app\service\runtime\MonitorState;
use app\service\runtime\SettingMonitorState;

class EpayOrderService
{
    public static function create(array $params): array
    {
        $config = EpayConfigService::requireEnabledConfig();
        static::validateRequest($params, $config);

        $type = static::mapType((string)$params['type']);
        $price = static::normalizeMoney((string)$params['money']);
        $orderId = date('YmdHis') . random_int(1000, 9999);
        $merchantOrderId = trim((string)$params['out_trade_no']);

        OrderCreationKernel::assertMerchantOrderNotExists($merchantOrderId);
        $reallyPrice = OrderCreationKernel::reserveUniquePrice($price, $type, $orderId);

        try {
            $payConfig = OrderCreationKernel::resolvePayUrl($type, (float)$reallyPrice);

            $createDate = time();
            $data = [
                'close_date' => 0,
                'create_date' => $createDate,
                'is_auto' => $payConfig['isAuto'],
                'notify_url' => trim((string)$params['notify_url']),
                'order_id' => $orderId,
                'param' => 'epay:' . (string)($params['param'] ?? ''),
                'pay_date' => 0,
                'pay_id' => $merchantOrderId,
                'pay_url' => $payConfig['payUrl'],
                'price' => (float)$price,
                'really_price' => (float)$reallyPrice,
                'return_url' => trim((string)$params['return_url']),
                'state' => PayOrder::STATE_UNPAID,
                'type' => $type,
            ];

            OrderCreationKernel::createOrderRecord($data);
        } catch (\Throwable $e) {
            OrderCreationKernel::rollbackReservedPrice($orderId);
            throw $e;
        }

        OrderCreationKernel::buildAndCacheOrderInfo(
            $merchantOrderId,
            $orderId,
            $type,
            $price,
            (float)$reallyPrice,
            $payConfig['payUrl'],
            $payConfig['isAuto'],
            $createDate
        );

        return [
            'trade_no' => $orderId,
            'payurl' => $payConfig['payUrl'],
            'qrcode' => $payConfig['payUrl'],
            'urlscheme' => '',
        ];
    }

    public static function createV2(array $params): array
    {
        $config = EpayConfigService::requireEnabledConfigV2();
        static::validateRequestV2($params, $config);

        $type = static::mapType((string)$params['type']);
        $price = static::normalizeMoney((string)$params['money']);
        $orderId = date('YmdHis') . random_int(1000, 9999);
        $merchantOrderId = trim((string)$params['out_trade_no']);

        OrderCreationKernel::assertMerchantOrderNotExists($merchantOrderId);
        $reallyPrice = OrderCreationKernel::reserveUniquePrice($price, $type, $orderId);

        try {
            $payConfig = OrderCreationKernel::resolvePayUrl($type, (float)$reallyPrice);

            $createDate = time();
            $data = [
                'close_date' => 0,
                'create_date' => $createDate,
                'is_auto' => $payConfig['isAuto'],
                'notify_url' => trim((string)$params['notify_url']),
                'order_id' => $orderId,
                'param' => 'epayv2:' . (string)($params['param'] ?? ''),
                'pay_date' => 0,
                'pay_id' => $merchantOrderId,
                'pay_url' => $payConfig['payUrl'],
                'price' => (float)$price,
                'really_price' => (float)$reallyPrice,
                'return_url' => trim((string)$params['return_url']),
                'state' => PayOrder::STATE_UNPAID,
                'type' => $type,
            ];

            OrderCreationKernel::createOrderRecord($data);
        } catch (\Throwable $e) {
            OrderCreationKernel::rollbackReservedPrice($orderId);
            throw $e;
        }

        OrderCreationKernel::buildAndCacheOrderInfo(
            $merchantOrderId,
            $orderId,
            $type,
            $price,
            (float)$reallyPrice,
            $payConfig['payUrl'],
            $payConfig['isAuto'],
            $createDate
        );

        $payType = static::detectPayType($payConfig['payUrl']);

        return [
            'trade_no' => $orderId,
            'pay_type' => $payType,
            'pay_info' => $payConfig['payUrl'],
        ];
    }

    private static function validateRequestV2(array $params, array $config): void
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

        if (mb_strlen((string)$params['out_trade_no']) > 100) {
            throw new \RuntimeException('商户订单号长度超限');
        }

        if (!isset($params['money']) || !is_scalar($params['money'])) {
            throw new \RuntimeException('金额格式错误');
        }

        $money = trim((string)$params['money']);
        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $money)) {
            throw new \RuntimeException('金额格式错误');
        }

        $normalizedMoney = static::normalizeMoney($money);
        if ((float)$normalizedMoney <= 0) {
            throw new \RuntimeException('订单金额必须大于0');
        }

        if ((float)$normalizedMoney > 999999.99) {
            throw new \RuntimeException('订单金额超出限制');
        }

        if (!isset($params['notify_url']) || trim((string)$params['notify_url']) === '') {
            throw new \RuntimeException('异步通知地址不能为空');
        }

        $notifyUrl = trim((string)$params['notify_url']);
        if (mb_strlen($notifyUrl) > 1000) {
            throw new \RuntimeException('回调地址长度超限');
        }

        if (!static::isValidHttpUrl($notifyUrl)) {
            throw new \RuntimeException('异步通知地址格式错误');
        }

        if (!isset($params['return_url']) || trim((string)$params['return_url']) === '') {
            throw new \RuntimeException('同步跳转地址不能为空');
        }

        $returnUrl = trim((string)$params['return_url']);
        if (mb_strlen($returnUrl) > 1000) {
            throw new \RuntimeException('返回地址长度超限');
        }

        if (!static::isValidHttpUrl($returnUrl)) {
            throw new \RuntimeException('同步跳转地址格式错误');
        }

        $timestamp = (int)($params['timestamp'] ?? 0);
        if ($timestamp === 0) {
            throw new \RuntimeException('缺少时间戳');
        }

        if (abs(time() - $timestamp) > 300) {
            throw new \RuntimeException('请求已过期');
        }

        if (!EpaySignService::verifyRsa($params, $config['public_key'])) {
            throw new \RuntimeException('签名校验失败');
        }

        if (!static::monitorState()->isOnline()) {
            throw new \RuntimeException('监控端状态异常，请检查');
        }
    }

    private static function detectPayType(string $payUrl): string
    {
        $lower = strtolower($payUrl);
        if (str_starts_with($lower, 'weixin://') || str_starts_with($lower, 'alipays://')) {
            return 'qrcode';
        }
        return 'url';
    }

    private static function mapType(string $type): int
    {
        return match ($type) {
            'wxpay' => PayOrder::TYPE_WECHAT,
            'alipay' => PayOrder::TYPE_ALIPAY,
            default => throw new \RuntimeException('不支持的支付类型'),
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

        if (mb_strlen((string)$params['out_trade_no']) > 100) {
            throw new \RuntimeException('商户订单号长度超限');
        }

        if (!isset($params['money']) || !is_scalar($params['money'])) {
            throw new \RuntimeException('金额格式错误');
        }

        $money = trim((string)$params['money']);
        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $money)) {
            throw new \RuntimeException('金额格式错误');
        }

        $normalizedMoney = static::normalizeMoney($money);
        if ((float)$normalizedMoney <= 0) {
            throw new \RuntimeException('订单金额必须大于0');
        }

        if ((float)$normalizedMoney > 999999.99) {
            throw new \RuntimeException('订单金额超出限制');
        }

        if (!isset($params['notify_url']) || trim((string)$params['notify_url']) === '') {
            throw new \RuntimeException('异步通知地址不能为空');
        }

        $notifyUrl = trim((string)$params['notify_url']);
        if (mb_strlen($notifyUrl) > 1000) {
            throw new \RuntimeException('回调地址长度超限');
        }

        if (!static::isValidHttpUrl($notifyUrl)) {
            throw new \RuntimeException('异步通知地址格式错误');
        }

        if (!isset($params['return_url']) || trim((string)$params['return_url']) === '') {
            throw new \RuntimeException('同步跳转地址不能为空');
        }

        $returnUrl = trim((string)$params['return_url']);
        if (mb_strlen($returnUrl) > 1000) {
            throw new \RuntimeException('返回地址长度超限');
        }

        if (!static::isValidHttpUrl($returnUrl)) {
            throw new \RuntimeException('同步跳转地址格式错误');
        }

        if (!EpaySignService::verifyMd5($params, $config['key'])) {
            throw new \RuntimeException('签名校验失败');
        }

        if (!static::monitorState()->isOnline()) {
            throw new \RuntimeException('监控端状态异常，请检查');
        }
    }

    private static function normalizeMoney(string $money): string
    {
        if (str_contains($money, '.')) {
            [$integer, $decimal] = explode('.', $money, 2);
            return $integer . '.' . str_pad($decimal, 2, '0');
        }

        return $money . '.00';
    }

    private static function isValidHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    protected static function monitorState(): MonitorState
    {
        return new SettingMonitorState();
    }
}
