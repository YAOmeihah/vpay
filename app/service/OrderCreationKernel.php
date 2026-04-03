<?php
declare(strict_types=1);

namespace app\service;

use app\model\PayOrder;
use app\model\PayQrcode;
use app\model\Setting;
use app\model\TmpPrice;
use app\service\cache\OrderCache;
use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\order\OrderPayloadFactory;

class OrderCreationKernel
{
    public static function assertMerchantOrderNotExists(string $merchantOrderId): void
    {
        $exists = PayOrder::where('pay_id', $merchantOrderId)->find();
        if ($exists) {
            throw new \RuntimeException('商户订单号已存在');
        }
    }

    public static function reserveUniquePrice(string $price, int $type, string $orderId): string
    {
        $reallyPrice = bcmul($price, '100');
        $payQf = static::systemConfig()->getPayQfMode();

        for ($i = 0; $i < 10; $i++) {
            $tmpPrice = $reallyPrice . '-' . $type;

            try {
                TmpPrice::create(['price' => $tmpPrice, 'oid' => $orderId]);
                return (string)bcdiv((string)$reallyPrice, '100', 2);
            } catch (\Exception $e) {
                // 价格冲突，继续尝试下一个金额
            }

            if ($payQf == 1 || $payQf == '1') {
                $reallyPrice++;
            } elseif ($payQf == 2 || $payQf == '2') {
                $reallyPrice--;
            }
        }

        throw new \RuntimeException('订单超出负荷，请稍后重试');
    }

    public static function resolvePayUrl(int $type, float|string $reallyPrice): array
    {
        $payUrl = static::getConfigPayUrl($type);
        if ($payUrl === '') {
            throw new \RuntimeException('请您先进入后台配置程序');
        }

        $matchedQrcode = PayQrcode::where('price', $reallyPrice)
            ->where('type', $type)
            ->find();

        if ($matchedQrcode) {
            return [
                'payUrl' => (string)$matchedQrcode['pay_url'],
                'isAuto' => 0,
            ];
        }

        return [
            'payUrl' => $payUrl,
            'isAuto' => 1,
        ];
    }

    public static function createOrderRecord(array $data): void
    {
        PayOrder::create($data);
    }

    public static function rollbackReservedPrice(string $orderId): void
    {
        TmpPrice::where('oid', $orderId)->delete();
    }

    public static function buildAndCacheOrderInfo(
        string $merchantOrderId,
        string $orderId,
        int $type,
        string $price,
        float|string $reallyPrice,
        string $payUrl,
        int $isAuto,
        int $createDate
    ): array {
        $rawTimeout = Setting::getConfigValue('close');

        $orderInfo = static::payloadFactory()->create(
            $merchantOrderId,
            $orderId,
            $type,
            $price,
            $reallyPrice,
            $payUrl,
            $isAuto,
            PayOrder::STATE_UNPAID,
            $rawTimeout,
            $createDate
        );

        static::orderCache()->cacheOrder($orderId, $orderInfo);

        return $orderInfo;
    }

    private static function getConfigPayUrl(int $type): string
    {
        if ($type === PayOrder::TYPE_WECHAT) {
            return static::systemConfig()->getWeChatPayUrl();
        }

        if ($type === PayOrder::TYPE_ALIPAY) {
            return static::systemConfig()->getAlipayPayUrl();
        }

        return '';
    }

    protected static function systemConfig(): SystemConfig
    {
        return new SettingSystemConfig();
    }

    protected static function orderCache(): OrderCache
    {
        return new OrderCache();
    }

    protected static function payloadFactory(): OrderPayloadFactory
    {
        return new OrderPayloadFactory();
    }
}
