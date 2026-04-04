<?php
declare(strict_types=1);

namespace app\service\cache;

use app\service\CacheService;

class CacheMaintenanceService
{
    public function clearAll(): bool
    {
        return CacheService::clearAll();
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return CacheService::getCacheStats();
    }
}
