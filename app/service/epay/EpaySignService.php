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
