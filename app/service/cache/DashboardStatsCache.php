<?php
declare(strict_types=1);

namespace app\service\cache;

use app\service\CacheService;

class DashboardStatsCache
{
    public function cacheStats(array $stats): bool
    {
        return CacheService::cacheStats('dashboard', $stats);
    }

    public function getStats()
    {
        return CacheService::getStats('dashboard');
    }

    public function deleteStats(): bool
    {
        return CacheService::deleteStats('dashboard');
    }
}
