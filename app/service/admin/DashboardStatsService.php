<?php
declare(strict_types=1);

namespace app\service\admin;

use app\service\cache\DashboardStatsCache;

class DashboardStatsService
{
    /**
     * @param callable(): array<string, mixed> $resolver
     * @return array<string, mixed>
     */
    public function getStats(callable $resolver): array
    {
        $cachedStats = $this->dashboardCache()->getStats();
        if (is_array($cachedStats) && $cachedStats !== []) {
            return $cachedStats;
        }

        $stats = $resolver();
        $this->dashboardCache()->cacheStats($stats);

        return $stats;
    }

    /**
     * @param array<string, int|float|string> $metrics
     * @param array<string, mixed> $system
     * @return array<string, mixed>
     */
    public function buildPayload(array $metrics, array $system): array
    {
        return [
            'todayOrder' => $metrics['todayOrder'],
            'todaySuccessOrder' => $metrics['todaySuccessOrder'],
            'todayCloseOrder' => $metrics['todayCloseOrder'],
            'todayMoney' => $metrics['todayMoney'],
            'countOrder' => $metrics['countOrder'],
            'countMoney' => $metrics['countMoney'],
            'PHP_VERSION' => $system['PHP_VERSION'],
            'PHP_OS' => $system['PHP_OS'],
            'SERVER' => $system['SERVER'],
            'MySql' => $system['MySql'],
            'Thinkphp' => $system['Thinkphp'],
            'RunTime' => $system['RunTime'],
            'ver' => $this->versionLabel(),
            'gd' => $system['gd'],
        ];
    }

    public function clearStats(): bool
    {
        return $this->dashboardCache()->deleteStats();
    }

    protected function dashboardCache(): DashboardStatsCache
    {
        return new DashboardStatsCache();
    }

    protected function versionLabel(): string
    {
        return 'v' . config('app.ver');
    }
}
