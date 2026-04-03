<?php
declare(strict_types=1);

namespace app\service\epay;

class EpayResponseService
{
    public static function success(array $payload): array
    {
        $tradeNo = trim((string)($payload['trade_no'] ?? ''));
        $payurl = trim((string)($payload['payurl'] ?? ''));
        $qrcode = trim((string)($payload['qrcode'] ?? ''));
        $urlscheme = trim((string)($payload['urlscheme'] ?? ''));

        if ($tradeNo === '') {
            throw new \InvalidArgumentException('缺少平台订单号');
        }

        if ($payurl === '' && $qrcode === '' && $urlscheme === '') {
            throw new \InvalidArgumentException('缺少支付信息');
        }

        return [
            'code' => 1,
            'msg' => 'success',
            'trade_no' => $tradeNo,
            'payurl' => $payurl,
            'qrcode' => $qrcode,
            'urlscheme' => $urlscheme,
        ];
    }

    public static function successV2(array $payload): array
    {
        $tradeNo = trim((string)($payload['trade_no'] ?? ''));
        $payType = trim((string)($payload['pay_type'] ?? ''));
        $payInfo = trim((string)($payload['pay_info'] ?? ''));

        if ($tradeNo === '') {
            throw new \InvalidArgumentException('缺少平台订单号');
        }

        if ($payInfo === '') {
            throw new \InvalidArgumentException('缺少支付信息');
        }

        return [
            'code' => 0,
            'msg' => 'success',
            'trade_no' => $tradeNo,
            'pay_type' => $payType,
            'pay_info' => $payInfo,
        ];
    }

    public static function fail(string $message): array
    {
        return [
            'code' => -1,
            'msg' => $message,
        ];
    }
}
