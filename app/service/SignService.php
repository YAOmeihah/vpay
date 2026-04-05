<?php
declare(strict_types=1);

namespace app\service;

use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;

class SignService
{
    /**
     * 生成原生 VPay 签名（MD5）
     * 签名规则：payId + param + type + price + reallyPrice + key
     */
    public static function makeOrderSign(
        string $payId,
        string $param,
        int $type,
        string $price,
        string $reallyPrice
    ): string {
        $key = static::systemConfig()->getSignKey();
        return md5($payId . $param . $type . $price . $reallyPrice . $key);
    }

    /**
     * 构建带签名的回调查询字符串
     * @param bool $formatPrice 是否对 price/reallyPrice 做 number_format
     *   - checkOrder (同步跳转) 场景：true（与原代码一致）
     *   - appPush (异步通知) 场景：false（保持原始值，与原代码一致）
     */
    public static function buildNotifyQuery(array $order, bool $formatPrice = false): string
    {
        if ($formatPrice) {
            $price = number_format((float)$order['price'], 2, '.', '');
            $reallyPrice = number_format((float)$order['really_price'], 2, '.', '');
        } else {
            $price = (string)$order['price'];
            $reallyPrice = (string)$order['really_price'];
        }

        $sign = static::makeOrderSign(
            (string)$order['pay_id'],
            (string)$order['param'],
            (int)$order['type'],
            $price,
            $reallyPrice
        );

        return http_build_query([
            'payId' => $order['pay_id'],
            'param' => $order['param'],
            'type' => $order['type'],
            'price' => $price,
            'reallyPrice' => $reallyPrice,
            'sign' => $sign,
        ]);
    }

    /**
     * 构建带签名的完整回调 URL
     * @param bool $formatPrice 透传给 buildNotifyQuery
     */
    public static function buildSignedUrl(string $baseUrl, array $order, bool $formatPrice = false): string
    {
        $query = static::buildNotifyQuery($order, $formatPrice);

        if (str_contains($baseUrl, '?')) {
            return $baseUrl . '&' . $query;
        }

        return $baseUrl . '?' . $query;
    }

    /**
     * 验证 createOrder 请求签名
     * 签名规则：payId + param + type + price + key
     */
    public static function verifyCreateOrderSign(
        string $payId,
        string $param,
        int $type,
        string $price,
        string $sign
    ): bool {
        $key = static::systemConfig()->getSignKey();
        $expected = md5($payId . $param . $type . $price . $key);
        return hash_equals($expected, $sign);
    }

    /**
     * 验证简单签名（用于 closeOrder, getState, appHeart, appPush）
     */
    public static function verifySimpleSign(string $data, string $sign): bool
    {
        $key = static::systemConfig()->getSignKey();
        return hash_equals(md5($data . $key), $sign);
    }

    public static function verifyMonitorPushSign(
        int $type,
        int $amountCents,
        int $ts,
        string $nonce,
        string $eventId,
        string $sign
    ): bool {
        $payload = implode('|', [$type, $amountCents, $ts, $nonce, $eventId]);
        $expected = hash_hmac('sha256', $payload, static::systemConfig()->getMonitorSignKey());

        return hash_equals($expected, $sign);
    }

    protected static function systemConfig(): SystemConfig
    {
        return new SettingSystemConfig();
    }
}
