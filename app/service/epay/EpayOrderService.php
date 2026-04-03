<?php
declare(strict_types=1);

namespace app\service\epay;

use app\model\PayOrder;
use app\model\PayQrcode;
use app\model\Setting;
use app\model\TmpPrice;

class EpayOrderService
{
    public static function create(array $params): array
    {
        $config = EpayConfigService::requireEnabledConfig();
        static::validateRequest($params, $config);

        $type = static::mapType((string)$params['type']);
        $price = static::normalizeMoney((string)$params['money']);
        $reallyPrice = bcmul($price, '100');
        $payQf = Setting::getConfigValue('payQf');
        $orderId = date('YmdHis') . random_int(1000, 9999);
        $merchantOrderId = trim((string)$params['out_trade_no']);

        $exists = PayOrder::where('pay_id', $merchantOrderId)->find();
        if ($exists) {
            throw new \RuntimeException('商户订单号已存在');
        }

        $ok = false;
        for ($i = 0; $i < 10; $i++) {
            $tmpPrice = $reallyPrice . '-' . $type;

            try {
                TmpPrice::create(['price' => $tmpPrice, 'oid' => $orderId]);
                $ok = true;
                break;
            } catch (\Exception $e) {
            }

            if ($payQf == '1') {
                $reallyPrice++;
            } elseif ($payQf == '2') {
                $reallyPrice--;
            }
        }

        if (!$ok) {
            throw new \RuntimeException('订单超出负荷，请稍后重试');
        }

        $tmpPrice = $reallyPrice . '-' . $type;

        try {
            $reallyPrice = (float)bcdiv((string)$reallyPrice, '100', 2);
            $payUrl = static::getPayUrl($type, $reallyPrice);

            if ($payUrl === '') {
                throw new \RuntimeException('请您先进入后台配置程序');
            }

            $createDate = time();
            $data = [
                'close_date' => 0,
                'create_date' => $createDate,
                'is_auto' => $payUrl === static::getConfigPayUrl($type) ? 1 : 0,
                'notify_url' => trim((string)$params['notify_url']),
                'order_id' => $orderId,
                'param' => 'epay:' . (string)($params['param'] ?? ''),
                'pay_date' => 0,
                'pay_id' => $merchantOrderId,
                'pay_url' => $payUrl,
                'price' => (float)$price,
                'really_price' => $reallyPrice,
                'return_url' => trim((string)$params['return_url']),
                'state' => PayOrder::STATE_UNPAID,
                'type' => $type,
            ];

            PayOrder::create($data);
        } catch (\Throwable $e) {
            TmpPrice::where('price', $tmpPrice)->where('oid', $orderId)->delete();
            throw $e;
        }

        $time = Setting::getConfigValue('close');
        \app\service\CacheService::cacheOrder($orderId, [
            'payId' => (string)$params['out_trade_no'],
            'orderId' => $orderId,
            'payType' => $type,
            'price' => $price,
            'reallyPrice' => $reallyPrice,
            'payUrl' => $payUrl,
            'isAuto' => $data['is_auto'],
            'state' => PayOrder::STATE_UNPAID,
            'timeOut' => $time,
            'date' => $createDate,
        ]);

        return [
            'trade_no' => $orderId,
            'payurl' => $payUrl,
            'qrcode' => $payUrl,
            'urlscheme' => '',
        ];
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

        if (Setting::getConfigValue('jkstate') !== '1') {
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

    private static function getConfigPayUrl(int $type): string
    {
        if ($type === PayOrder::TYPE_WECHAT) {
            return Setting::getConfigValue('wxpay');
        }

        if ($type === PayOrder::TYPE_ALIPAY) {
            return Setting::getConfigValue('zfbpay');
        }

        return '';
    }

    private static function getPayUrl(int $type, float $reallyPrice): string
    {
        $payUrl = static::getConfigPayUrl($type);
        $matchedQrcode = PayQrcode::where('price', $reallyPrice)
            ->where('type', $type)
            ->find();

        if ($matchedQrcode) {
            return (string)$matchedQrcode['pay_url'];
        }

        return $payUrl;
    }
}
