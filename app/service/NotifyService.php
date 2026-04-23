<?php
declare(strict_types=1);

namespace app\service;

use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\payment\PaymentTestLabService;

class NotifyService
{
    /**
     * 发送商户异步通知
     */
    public static function sendNotify(array $order): bool
    {
        return static::sendNotifyDetailed($order)['ok'];
    }

    /**
     * 发送商户异步通知并返回失败详情，便于补单页面排障。
     *
     * @return array{ok: bool, detail: string, response: string}
     */
    public static function sendNotifyDetailed(array $order): array
    {
        return static::sendNativeNotifyDetailed($order);
    }

    /**
     * 构建同步跳转 URL，checkOrder 调用时保持原格式
     */
    public static function buildReturnUrl(array $order): string
    {
        return SignService::buildSignedUrl($order['return_url'], $order, true);
    }

    private static function sendNativeNotifyDetailed(array $order): array
    {
        $url = SignService::buildSignedUrl(
            trim((string)$order['notify_url']),
            $order
        );

        if (static::isPaymentTestLabNotifyUrl($url)) {
            parse_str((string)(parse_url($url, PHP_URL_QUERY) ?? ''), $payload);
            static::paymentTestLabService()->recordCallback('notify', $payload);

            return [
                'ok' => true,
                'detail' => '',
                'response' => 'success',
            ];
        }

        $httpResult = static::httpGetDetailed($url);
        $response = trim($httpResult['response']);

        if ($response === 'success') {
            return [
                'ok' => true,
                'detail' => '',
                'response' => $response,
            ];
        }

        $detail = static::buildFailureDetail($httpResult, $response);

        return [
            'ok' => false,
            'detail' => $detail,
            'response' => $response,
        ];
    }

    /**
     * 安全的 HTTP GET 请求
     * - 默认启用 SSL 验证（可通过后台 notify_ssl_verify 设置关闭，兼容自签名证书商户）
     * - 禁止跟随重定向（防 SSRF）
     * - 超时 10 秒
     */
    public static function httpGet(string $url): string
    {
        return static::httpGetDetailed($url)['response'];
    }

    /**
     * 安全的 HTTP GET 请求，并返回调试信息。
     *
     * @return array{
     *   response: string,
     *   error: string,
     *   errno: int,
     *   httpCode: int,
     *   effectiveUrl: string,
     *   primaryIp: string
     * }
     */
    protected static function httpGetDetailed(string $url): array
    {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true)) {
            return [
                'response' => '',
                'error' => '通知地址协议不受支持',
                'errno' => 0,
                'httpCode' => 0,
                'effectiveUrl' => $url,
                'primaryIp' => '',
            ];
        }

        // 防止 SSRF：统一检查域名解析结果和 IP 字面量，拦截私网/保留地址。
        $host = trim((string) ($parsed['host'] ?? ''), '[]');
        if ($host !== '') {
            foreach (static::resolveHostIps($host) as $ip) {
                if (!static::isPrivateIp($ip)) {
                    continue;
                }

                return [
                    'response' => '',
                    'error' => '通知地址指向内网地址，已被安全策略拦截',
                    'errno' => 0,
                    'httpCode' => 0,
                    'effectiveUrl' => $url,
                    'primaryIp' => $ip,
                ];
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
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) (curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0);
        $effectiveUrl = (string) (curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
        $primaryIp = (string) (curl_getinfo($ch, CURLINFO_PRIMARY_IP) ?: '');
        curl_close($ch);

        return [
            'response' => is_string($result) ? $result : '',
            'error' => trim($error),
            'errno' => $errno,
            'httpCode' => $httpCode,
            'effectiveUrl' => $effectiveUrl,
            'primaryIp' => $primaryIp,
        ];
    }

    /**
     * @param array{
     *   response: string,
     *   error: string,
     *   errno: int,
     *   httpCode: int,
     *   effectiveUrl: string,
     *   primaryIp: string
     * } $httpResult
     */
    protected static function buildFailureDetail(array $httpResult, string $response): string
    {
        if ($httpResult['error'] !== '') {
            $parts = ['通知请求失败: ' . $httpResult['error']];

            if (($httpResult['errno'] ?? 0) > 0) {
                $parts[] = 'curl_errno=' . $httpResult['errno'];
            }

            if (($httpResult['httpCode'] ?? 0) > 0) {
                $parts[] = 'http_code=' . $httpResult['httpCode'];
            }

            if (($httpResult['primaryIp'] ?? '') !== '') {
                $parts[] = 'primary_ip=' . $httpResult['primaryIp'];
            }

            if (($httpResult['effectiveUrl'] ?? '') !== '') {
                $parts[] = 'effective_url=' . $httpResult['effectiveUrl'];
            }

            return implode('; ', $parts);
        }

        return $response !== '' ? '通知接口返回: ' . $response : '通知接口未返回 success';
    }

    private static function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * @return array<int, string>
     */
    private static function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        if (function_exists('dns_get_record')) {
            $types = DNS_A;
            if (defined('DNS_AAAA')) {
                $types |= DNS_AAAA;
            }

            $records = @dns_get_record($host, $types);
            if (is_array($records)) {
                foreach ($records as $record) {
                    $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
                    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        $resolved = gethostbyname($host);
        if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
            $ips[] = $resolved;
        }

        return array_values(array_unique($ips));
    }

    protected static function systemConfig(): SystemConfig
    {
        return app()->make(SettingSystemConfig::class);
    }

    protected static function paymentTestLabService(): PaymentTestLabService
    {
        return app()->make(PaymentTestLabService::class);
    }

    private static function isPaymentTestLabNotifyUrl(string $url): bool
    {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        if ($path !== '/payment-test/notify') {
            return false;
        }

        parse_str((string)(parse_url($url, PHP_URL_QUERY) ?? ''), $query);

        return (string)($query['vpayPaymentLab'] ?? '') === '1';
    }
}
