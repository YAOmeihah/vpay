<?php
declare(strict_types=1);

namespace tests;

use app\service\security\MonitorReplayGuard;
use PHPUnit\Framework\TestCase;

class MonitorReplayGuardTest extends TestCase
{
    public function test_accepts_first_event_once(): void
    {
        $guard = new MonitorReplayGuardProbe(1712300000);

        $result = $guard->assertValid('evt-accept-1', 'nonce-accept-1', 1712300000);

        $this->assertSame('accepted', $result);
    }

    public function test_returns_duplicate_for_same_event_id(): void
    {
        $guard = new MonitorReplayGuardProbe(1712300000);
        $timestamp = 1712300000;

        $guard->assertValid('evt-duplicate-1', 'nonce-duplicate-1', $timestamp);
        $result = $guard->assertValid('evt-duplicate-1', 'nonce-duplicate-2', $timestamp);

        $this->assertSame('duplicate', $result);
    }

    public function test_rejects_reused_nonce_for_different_event(): void
    {
        $guard = new MonitorReplayGuardProbe(1712300000);
        $timestamp = 1712300000;

        $guard->assertValid('evt-replay-1', 'nonce-replay-1', $timestamp);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('监控回调 nonce 重放');

        $guard->assertValid('evt-replay-2', 'nonce-replay-1', $timestamp);
    }

    public function test_rejects_expired_timestamp(): void
    {
        $guard = new MonitorReplayGuardProbe(1712300000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('监控回调时间戳已失效');

        $guard->assertValid('evt-expired-1', 'nonce-expired-1', 1712290000);
    }
}

final class MonitorReplayGuardProbe extends MonitorReplayGuard
{
    /**
     * @var array<string, mixed>
     */
    private array $store = [];

    public function __construct(private readonly int $now)
    {
    }

    protected function currentTimestamp(): int
    {
        return $this->now;
    }

    protected function getValue(string $key): mixed
    {
        return $this->store[$key] ?? null;
    }

    protected function putValue(string $key, mixed $value, int $ttl): void
    {
        $this->store[$key] = ['value' => $value, 'ttl' => $ttl];
    }
}
