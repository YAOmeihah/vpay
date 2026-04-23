<?php
declare(strict_types=1);

namespace app\service;

use app\model\PayOrder;
use app\model\PayQrcode;
use app\model\TerminalChannel;
use app\model\TmpPrice;
use app\service\cache\OrderCache;
use app\service\config\SystemConfig;
use app\service\order\OrderPayloadFactory;
use app\service\terminal\ChannelPriceReservationService;

class OrderCreationKernel
{
    public static function generatePlatformOrderId(): string
    {
        return date('YmdHis') . random_int(1000, 9999);
    }

    public static function assertMerchantOrderNotExists(string $merchantOrderId): void
    {
        $exists = PayOrder::where('pay_id', $merchantOrderId)->find();
        if ($exists) {
            throw new \RuntimeException('商户订单号已存在');
        }
    }

    public static function reserveUniquePrice(string $price, int $type, string $orderId): string
    {
        return static::priceReservation()->reserve(
            $price,
            $type,
            $orderId,
            static::systemConfig()->getPayQfMode()
        );
    }

    public static function resolvePayUrlForChannel(int $channelId, int $type, float|string $reallyPrice): array
    {
        $matchedQrcode = PayQrcode::where('channel_id', $channelId)
            ->where('price', $reallyPrice)
            ->find();

        if ($matchedQrcode) {
            return [
                'payUrl' => (string) $matchedQrcode['pay_url'],
                'isAuto' => 0,
            ];
        }

        $channel = TerminalChannel::where('id', $channelId)
            ->where('type', $type)
            ->find();

        if (!$channel) {
            throw new \RuntimeException('当前通道不存在或已被删除');
        }

        $payUrl = trim((string) $channel['pay_url']);
        if ($payUrl === '') {
            throw new \RuntimeException('请您先进入后台配置程序');
        }

        return [
            'payUrl' => $payUrl,
            'isAuto' => 1,
        ];
    }

    public static function createOrderRecord(array $data): void
    {
        try {
            PayOrder::create($data);
        } catch (\Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                throw new \RuntimeException('订单重复，请重试', 0, $e);
            }

            throw $e;
        }
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
        int $createDate,
        int $terminalId = 0,
        int $channelId = 0,
        string $terminalSnapshot = '',
        string $channelSnapshot = ''
    ): array {
        $orderInfo = static::payloadFactory()->create(
            $merchantOrderId,
            $orderId,
            $type,
            $price,
            $reallyPrice,
            $payUrl,
            $isAuto,
            PayOrder::STATE_UNPAID,
            static::systemConfig()->getOrderCloseRaw(),
            $createDate
        );

        $orderInfo['terminalId'] = $terminalId;
        $orderInfo['channelId'] = $channelId;
        $orderInfo['terminalSnapshot'] = $terminalSnapshot;
        $orderInfo['channelSnapshot'] = $channelSnapshot;

        static::orderCache()->cacheOrder($orderId, $orderInfo);

        return $orderInfo;
    }

    protected static function systemConfig(): SystemConfig
    {
        return app()->make(SystemConfig::class);
    }

    protected static function orderCache(): OrderCache
    {
        return app()->make(OrderCache::class);
    }

    protected static function payloadFactory(): OrderPayloadFactory
    {
        return app()->make(OrderPayloadFactory::class);
    }

    protected static function priceReservation(): ChannelPriceReservationService
    {
        return app()->make(ChannelPriceReservationService::class);
    }
}
