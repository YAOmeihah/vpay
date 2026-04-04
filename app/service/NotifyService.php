<?php
declare(strict_types=1);

namespace app\service;

use app\service\epay\EpayConfigService;
use app\service\epay\EpayNotifyService;
use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;

class NotifyService
{
    /**
     * 发送商户异步通知，自动区分 epay/原生订单
     */
    public static function sendNotify(array $order): bool
    {
        if (EpayNotifyService::isEpayOrder($order)) {
            return static::sendEpayNotify($order);
        }

        return static::sendNativeNotify($order);
    }

    /**
     * 构建同步跳转 URL，自动区分 epay/原生订单
     * 原生订单场景：checkOrder 调用，需要 formatPrice=true 与原代码一致
     */
    public static function buildReturnUrl(array $order): string
    {
        if (EpayNotifyService::isEpayOrder($order)) {
            $epayConfig = EpayConfigService::getConfig();
            $signingKey = EpayNotifyService::isEpayV2Order($order)
                ? $epayConfig['private_key']
                : $epayConfig['key'];
            return EpayNotifyService::buildReturnUrl($order, $signingKey);
        }

        return SignService::buildSignedUrl($order['return_url'], $order, true);
    }

    private static function sendEpayNotify(array $order): bool
    {
        $epayConfig = EpayConfigService::getConfig();
        $signingKey = EpayNotifyService::isEpayV2Order($order)
            ? $epayConfig['private_key']
            : $epayConfig['key'];
        return EpayNotifyService::sendNotify($order, $signingKey);
    }

    private static function sendNativeNotify(array $order): bool
    {
        $url = SignService::buildSignedUrl(
            trim((string)$order['notify_url']),
            $order
        );

        $response = static::httpGet($url);
        return trim($response) === 'success';
    }

    /**
     * 安全的 HTTP GET 请求
     * - 默认启用 SSL 验证（可通过后台 notify_ssl_verify 设置关闭，兼容自签名证书商户）
     * - 禁止跟随重定向（防 SSRF）
     * - 超时 10 秒
     */
    public static function httpGet(string $url): string
    {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        // 防止 SSRF：检查目标 IP 是否为内网地址
        $host = $parsed['host'] ?? '';
        if ($host !== '') {
            $ip = gethostbyname($host);
            if ($ip !== $host && static::isPrivateIp($ip)) {
                return '';
            }
        }

        // 读取 SSL 验证开关：默认 "1"（启用），后台可设为 "0" 兼容自签名证书
        $sslVerify = static::systemConfig()->getNotifySslVerifyEnabled();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        $result = curl_exec($ch);
        curl_close($ch);

        return is_string($result) ? $result : '';
    }

    private static function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    protected static function systemConfig(): SystemConfig
    {
        return app()->make(SettingSystemConfig::class);
    }
}
