<?php
declare(strict_types=1);

namespace app\service\epay;

use app\model\PayOrder;

class EpayNotifyService
{
    /**
     * 判断订单是否为 EPay 渠道创建的订单
     * EPay 订单的 param 字段以 "epay:" 前缀标记
     */
    public static function isEpayOrder(array $order): bool
    {
        $param = (string)($order['param'] ?? '');
        return str_starts_with($param, 'epay:');
    }

    /**
     * 获取 EPay 订单中商户原始 param 值
     */
    public static function getMerchantParam(array $order): string
    {
        $param = (string)($order['param'] ?? '');
        if (str_starts_with($param, 'epay:')) {
            return substr($param, 5);
        }
        return $param;
    }

    /**
     * 构建 EPay 异步通知回调参数
     * 符合易支付 v1 协议：pid, trade_no, out_trade_no, type, name, money, trade_status, sign, sign_type
     */
    public static function buildNotifyParams(array $order, string $key): array
    {
        $config = EpayConfigService::getConfig();
        $typeStr = static::reverseMapType((int)$order['type']);

        $params = [
            'pid' => $config['pid'],
            'trade_no' => (string)$order['order_id'],
            'out_trade_no' => (string)$order['pay_id'],
            'type' => $typeStr,
            'name' => $config['name'],
            'money' => number_format((float)$order['price'], 2, '.', ''),
            'trade_status' => 'TRADE_SUCCESS',
        ];

        $params['sign'] = EpaySignService::makeMd5($params, $key);
        $params['sign_type'] = 'MD5';

        return $params;
    }

    /**
     * 构建 EPay 同步跳转（return_url）完整 URL
     */
    public static function buildReturnUrl(array $order, string $key): string
    {
        $params = static::buildNotifyParams($order, $key);
        $returnUrl = trim((string)$order['return_url']);

        $query = http_build_query($params);

        if (str_contains($returnUrl, '?')) {
            return $returnUrl . '&' . $query;
        }

        return $returnUrl . '?' . $query;
    }

    /**
     * 发送 EPay 异步通知到商户
     * 返回 true 表示商户响应 "success"
     */
    public static function sendNotify(array $order, string $key): bool
    {
        $params = static::buildNotifyParams($order, $key);
        $notifyUrl = trim((string)$order['notify_url']);

        $query = http_build_query($params);

        if (str_contains($notifyUrl, '?')) {
            $url = $notifyUrl . '&' . $query;
        } else {
            $url = $notifyUrl . '?' . $query;
        }

        $response = static::httpGet($url);

        return trim($response) === 'success';
    }

    /**
     * 简单 HTTP GET 请求
     */
    private static function httpGet(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $result = curl_exec($ch);
        curl_close($ch);

        return is_string($result) ? $result : '';
    }

    private static function reverseMapType(int $type): string
    {
        return match ($type) {
            PayOrder::TYPE_WECHAT => 'wxpay',
            PayOrder::TYPE_ALIPAY => 'alipay',
            default => 'unknown',
        };
    }
}
