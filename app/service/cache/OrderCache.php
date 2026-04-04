<?php
declare(strict_types=1);

namespace app\service\cache;

use app\service\CacheService;

class OrderCache
{
    public function cacheOrder(string $orderId, array $orderData): bool
    {
        return CacheService::cacheOrder($orderId, $orderData);
    }

    public function getOrder(string $orderId): ?array
    {
        return CacheService::getOrder($orderId);
    }

    public function deleteOrder(string $orderId): bool
    {
        return CacheService::deleteOrder($orderId);
    }
}
