<?php
declare(strict_types=1);

namespace app\service;

use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\terminal\TerminalCredentialService;

class SignService
{
    public const SIGN_TYPE_MD5 = 'MD5';
    public const SIGN_TYPE_HMAC_SHA256 = 'HMAC_SHA256';

    /**
     * 生成原生 VPay 签名
     * 签名规则：payId + param + type + price + reallyPrice + key
     */
    public static function makeOrderSign(
        string $payId,
        string $param,
        int $type,
        string $price,
        string $reallyPrice,
        string $signType = self::SIGN_TYPE_MD5
    ): string {
        $key = static::systemConfig()->getSignKey();
        $payload = $payId . $param . $type . $price . $reallyPrice;

        return static::makeSignature($payload, $key, static::normalizeStoredSignType($signType));
    }

    /**
     * 生成 createOrder 请求签名
     * 签名规则：payId + param + type + price + key
     */
    public static function makeCreateOrderSign(
        string $payId,
        string $param,
        int $type,
        string $price,
        string $signType = self::SIGN_TYPE_MD5
    ): string {
        $normalizedSignType = static::normalizeRequestedSignType($signType);
        if ($normalizedSignType === null) {
            throw new \RuntimeException('签名算法不支持');
        }

        $key = static::systemConfig()->getSignKey();

        return static::makeSignature($payId . $param . $type . $price, $key, $normalizedSignType);
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

        $signType = static::normalizeStoredSignType((string)($order['sign_type'] ?? ''));
        $sign = static::makeOrderSign(
            (string)$order['pay_id'],
            (string)$order['param'],
            (int)$order['type'],
            $price,
            $reallyPrice,
            $signType
        );

        return http_build_query([
            'payId' => $order['pay_id'],
            'param' => $order['param'],
            'type' => $order['type'],
            'price' => $price,
            'reallyPrice' => $reallyPrice,
            'signType' => $signType,
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
        string $sign,
        string $signType = self::SIGN_TYPE_MD5
    ): bool {
        $normalizedSignType = static::normalizeRequestedSignType($signType);
        if ($normalizedSignType === null) {
            return false;
        }

        $key = static::systemConfig()->getSignKey();
        $expected = static::makeSignature($payId . $param . $type . $price, $key, $normalizedSignType);

        return hash_equals($expected, $sign);
    }

    /**
     * 验证简单签名（用于 closeOrder, getState, appHeart, appPush）
     */
    public static function verifySimpleSign(
        string $data,
        string $sign,
        string $signType = self::SIGN_TYPE_MD5
    ): bool {
        $normalizedSignType = static::normalizeRequestedSignType($signType);
        if ($normalizedSignType === null) {
            return false;
        }

        $key = static::systemConfig()->getSignKey();

        return hash_equals(static::makeSignature($data, $key, $normalizedSignType), $sign);
    }

    public static function verifyTerminalMonitorSimpleSign(string $terminalCode, string $data, string $sign): bool
    {
        $expected = md5($data . static::terminalCredentialService()->requireKeyFor(trim($terminalCode)));
        return hash_equals($expected, $sign);
    }

    public static function verifyTerminalMonitorPushSign(
        string $terminalCode,
        int $type,
        int $amountCents,
        int $ts,
        string $nonce,
        string $eventId,
        string $sign
    ): bool {
        $normalizedTerminalCode = trim($terminalCode);
        $payload = implode('|', [$normalizedTerminalCode, $type, $amountCents, $ts, $nonce, $eventId]);
        $expected = hash_hmac(
            'sha256',
            $payload,
            static::terminalCredentialService()->requireKeyFor($normalizedTerminalCode)
        );

        return hash_equals($expected, $sign);
    }

    public static function normalizeStoredSignType(?string $signType): string
    {
        return static::normalizeRequestedSignType($signType ?? '') ?? self::SIGN_TYPE_MD5;
    }

    public static function normalizeRequestedSignType(string $signType): ?string
    {
        $normalized = strtoupper(trim($signType));
        if ($normalized === '') {
            return self::SIGN_TYPE_MD5;
        }

        return match ($normalized) {
            self::SIGN_TYPE_MD5 => self::SIGN_TYPE_MD5,
            self::SIGN_TYPE_HMAC_SHA256 => self::SIGN_TYPE_HMAC_SHA256,
            default => null,
        };
    }

    private static function makeSignature(string $payload, string $key, string $signType): string
    {
        return match ($signType) {
            self::SIGN_TYPE_HMAC_SHA256 => hash_hmac('sha256', $payload, $key),
            default => md5($payload . $key),
        };
    }

    protected static function systemConfig(): SystemConfig
    {
        return new SettingSystemConfig();
    }

    protected static function terminalCredentialService(): TerminalCredentialService
    {
        return app()->make(TerminalCredentialService::class);
    }
}
