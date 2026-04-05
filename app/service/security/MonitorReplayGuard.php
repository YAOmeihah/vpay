<?php
declare(strict_types=1);

namespace app\service\security;

class MonitorReplayGuard
{
    private const ALLOWED_SKEW_SECONDS = 300;
    private const EVENT_TTL_SECONDS = 86400;
    private const NONCE_TTL_SECONDS = 600;

    public function assertValid(string $eventId, string $nonce, int $timestamp): string
    {
        if (abs($this->currentTimestamp() - $timestamp) > self::ALLOWED_SKEW_SECONDS) {
            throw new \RuntimeException('监控回调时间戳已失效');
        }

        $eventKey = 'monitor_push:event:' . sha1($eventId);
        if ($this->getValue($eventKey) !== null) {
            return 'duplicate';
        }

        $nonceKey = 'monitor_push:nonce:' . sha1($nonce);
        if ($this->getValue($nonceKey) !== null) {
            throw new \RuntimeException('监控回调 nonce 重放');
        }

        $this->putValue($eventKey, 1, self::EVENT_TTL_SECONDS);
        $this->putValue($nonceKey, 1, self::NONCE_TTL_SECONDS);

        return 'accepted';
    }

    protected function currentTimestamp(): int
    {
        return time();
    }

    protected function getValue(string $key): mixed
    {
        return cache($key);
    }

    protected function putValue(string $key, mixed $value, int $ttl): void
    {
        cache($key, $value, $ttl);
    }
}
