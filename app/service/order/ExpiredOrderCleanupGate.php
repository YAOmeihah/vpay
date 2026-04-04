<?php
declare(strict_types=1);

namespace app\service\order;

use think\facade\Cache;

class ExpiredOrderCleanupGate
{
    private const CACHE_KEY = 'monitor:expired-order-cleanup';

    public function __construct(private readonly int $ttlSeconds = 5)
    {
    }

    public function shouldRun(bool $force = false): bool
    {
        if ($force) {
            $this->markRun();
            return true;
        }

        try {
            if (Cache::has(self::CACHE_KEY)) {
                return false;
            }

            $this->markRun();
        } catch (\Throwable) {
            return true;
        }

        return true;
    }

    private function markRun(): void
    {
        Cache::set(self::CACHE_KEY, time(), $this->ttlSeconds);
    }
}
