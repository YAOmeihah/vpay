<?php
declare(strict_types=1);

namespace app\service\order;

use app\service\CacheService;

class OrderStateManager
{
    public function invalidateOrderView(?string $orderId): void
    {
        if (is_string($orderId) && $orderId !== '') {
            CacheService::deleteOrder($orderId);
        }

        CacheService::deleteStats('dashboard');
    }

    /**
     * @param iterable<int, string|null> $orderIds
     */
    public function invalidateOrderViews(iterable $orderIds): void
    {
        foreach ($orderIds as $orderId) {
            if (is_string($orderId) && $orderId !== '') {
                CacheService::deleteOrder($orderId);
            }
        }

        CacheService::deleteStats('dashboard');
    }
}
