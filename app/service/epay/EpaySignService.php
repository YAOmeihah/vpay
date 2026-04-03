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

    public static function makeRsa(array $params, string $privateKeyPem): string
    {
        $content = static::buildSignContent($params);
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if ($privateKey === false) {
            throw new \RuntimeException('RSA 私钥格式错误');
        }

        $signed = openssl_sign($content, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$signed) {
            throw new \RuntimeException('RSA 签名失败');
        }

        return base64_encode($signature);
    }

    public static function verifyRsa(array $params, string $publicKeyPem): bool
    {
        $inputSign = (string)($params['sign'] ?? '');
        if ($inputSign === '') {
            return false;
        }

        $content = static::buildSignContent($params);
        $publicKey = openssl_pkey_get_public($publicKeyPem);

        if ($publicKey === false) {
            return false;
        }

        $decoded = base64_decode($inputSign, true);
        if ($decoded === false) {
            return false;
        }

        return openssl_verify($content, $decoded, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
