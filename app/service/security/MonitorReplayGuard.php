<?php
declare(strict_types=1);

namespace app\service\security;

class MonitorReplayGuard
{
    private const ALLOWED_SKEW_MILLISECONDS = 300000;
    private const EVENT_TTL_SECONDS = 86400;
    private const NONCE_TTL_SECONDS = 600;

    public function assertValid(string $eventId, string $nonce, int $timestamp, string $scope = ''): string
    {
        $this->assertFreshTimestamp($timestamp);

        $eventKey = $this->scopedKey('monitor_push:event', $eventId, $scope);
        if ($this->getValue($eventKey) !== null) {
            return 'duplicate';
        }

        $nonceKey = $this->scopedKey('monitor_push:nonce', $nonce, $scope);
        if ($this->getValue($nonceKey) !== null) {
            throw new \RuntimeException('监控回调 nonce 重放');
        }

        $this->putValue($eventKey, 1, self::EVENT_TTL_SECONDS);
        $this->putValue($nonceKey, 1, self::NONCE_TTL_SECONDS);

        return 'accepted';
    }

    public function assertFreshTimestamp(int $timestamp): void
    {
        if ($timestamp <= 9999999999) {
            throw new \RuntimeException('监控回调时间戳已失效');
        }

        if (abs($this->currentTimestamp() - $timestamp) > self::ALLOWED_SKEW_MILLISECONDS) {
            throw new \RuntimeException('监控回调时间戳已失效');
        }
    }

    protected function currentTimestamp(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    protected function getValue(string $key): mixed
    {
        return cache($key);
    }

    protected function putValue(string $key, mixed $value, int $ttl): void
    {
        cache($key, $value, $ttl);
    }

    private function scopedKey(string $prefix, string $value, string $scope): string
    {
        $payload = $scope === '' ? $value : $scope . '|' . $value;

        return $prefix . ':' . sha1($payload);
    }
}
