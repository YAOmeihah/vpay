<?php
declare(strict_types=1);

namespace app\service\terminal;

use app\model\TmpPrice;

/**
 * 通道级金额占位服务
 */
class ChannelPriceReservationService
{
    public function reserve(string $price, int $channelId, string $orderId, string $mode): string
    {
        $cents = (int) bcmul($price, '100');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = number_format($cents / 100, 2, '.', '');

            try {
                TmpPrice::create([
                    'oid' => $orderId,
                    'channel_id' => $channelId,
                    'price' => $candidate,
                ]);

                return $candidate;
            } catch (\Throwable) {
                $cents += $mode === '2' ? -1 : 1;
            }
        }

        throw new \RuntimeException('订单超出负荷，请稍后重试');
    }
}
