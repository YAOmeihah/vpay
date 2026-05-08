<?php
declare(strict_types=1);

namespace tests;

use app\service\security\RateLimitExceededException;
use app\service\security\RateLimitPolicy;
use app\service\security\RequestRateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimitServiceTest extends TestCase
{
    private static \think\App $app;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$app = new \think\App(dirname(__DIR__) . DIRECTORY_SEPARATOR);
        self::$app->initialize();
    }

    public function test_request_rate_limiter_counts_per_policy_and_key(): void
    {
        $limiter = new RequestRateLimiterProbe(1000);
        $heartbeat = new RateLimitPolicy('monitor_heartbeat', 2, 60);
        $push = new RateLimitPolicy('monitor_push', 2, 60);

        $limiter->assertAllowed($heartbeat, 'terminal:T1');
        $limiter->assertAllowed($heartbeat, 'terminal:T2');
        $limiter->assertAllowed($push, 'terminal:T1');
        $limiter->assertAllowed($heartbeat, 'terminal:T1');

        $this->expectException(RateLimitExceededException::class);
        $limiter->assertAllowed($heartbeat, 'terminal:T1');
    }

    public function test_rate_limit_exception_reports_retry_after(): void
    {
        $limiter = new RequestRateLimiterProbe(1000);
        $policy = new RateLimitPolicy('default', 1, 60);

        $limiter->assertAllowed($policy, 'ip:127.0.0.1');

        try {
            $limiter->assertAllowed($policy, 'ip:127.0.0.1');
            $this->fail('Expected rate limit exception.');
        } catch (RateLimitExceededException $exception) {
            $this->assertSame('default', $exception->policy()->name());
            $this->assertSame('ip:127.0.0.1', $exception->key());
            $this->assertSame(60, $exception->retryAfter());
            $this->assertSame(1, $exception->maxRequests());
            $this->assertSame(60, $exception->windowSeconds());
        }
    }

    public function test_policy_resolver_maps_known_paths(): void
    {
        $resolver = new \app\service\security\RateLimitPolicyResolver();

        $this->assertSame('monitor_heartbeat', $resolver->resolve($this->requestForPath('appHeart'))->name());
        $this->assertSame('monitor_push', $resolver->resolve($this->requestForPath('appPush'))->name());
        $this->assertSame('monitor_query', $resolver->resolve($this->requestForPath('getState'))->name());
        $this->assertSame('monitor_query', $resolver->resolve($this->requestForPath('closeEndOrder'))->name());
        $this->assertSame('admin_login', $resolver->resolve($this->requestForPath('login'))->name());
        $this->assertSame('admin_api', $resolver->resolve($this->requestForPath('admin/index/profile'))->name());
        $this->assertSame('merchant_api', $resolver->resolve($this->requestForPath('createOrder'))->name());
        $this->assertSame('default', $resolver->resolve($this->requestForPath('unknown'))->name());
    }

    public function test_key_resolver_prefers_stable_identity_for_each_policy(): void
    {
        $resolver = new \app\service\security\RateLimitKeyResolver();

        $monitorRequest = $this->requestForPath('appHeart', ['terminalCode' => 'T100']);
        $this->assertSame(
            'terminal:T100',
            $resolver->resolve(new RateLimitPolicy('monitor_heartbeat', 30, 60), $monitorRequest)
        );

        $merchantRequest = $this->requestForPath('createOrder', ['pid' => 'merchant-1']);
        $this->assertSame(
            'merchant:merchant-1',
            $resolver->resolve(new RateLimitPolicy('merchant_api', 120, 60), $merchantRequest)
        );

        $fallbackRequest = $this->requestForPath('appPush', [], '10.0.0.9');
        $this->assertSame(
            'ip:10.0.0.9',
            $resolver->resolve(new RateLimitPolicy('monitor_push', 120, 60), $fallbackRequest)
        );
    }

    public function test_default_policy_ignores_legacy_rate_limit_config(): void
    {
        $originalSecurity = self::$app->config->get('security');
        $security = is_array($originalSecurity) ? $originalSecurity : [];
        unset($security['rate_limits']['default']);
        $security['rate_limit'] = [
            'max_requests' => 9,
            'window_seconds' => 11,
        ];
        self::$app->config->set($security, 'security');

        try {
            $resolver = new \app\service\security\RateLimitPolicyResolver();
            $policy = $resolver->resolve($this->requestForPath('unmatched'));

            $this->assertSame('default', $policy->name());
            $this->assertSame(120, $policy->maxRequests());
            $this->assertSame(60, $policy->windowSeconds());
        } finally {
            self::$app->config->set($originalSecurity, 'security');
        }
    }

    private function requestForPath(string $path, array $params = [], string $ip = '127.0.0.1'): \think\Request
    {
        $request = new \think\Request();
        $request->setPathinfo($path);
        $request->withServer(['REMOTE_ADDR' => $ip]);
        $request->withGet($params);
        $request->withPost($params);
        return $request;
    }
}

final class RequestRateLimiterProbe extends RequestRateLimiter
{
    /** @var array<string, array{count:int, reset_at:int}> */
    public array $store = [];

    public function __construct(private int $now)
    {
    }

    protected function now(): int
    {
        return $this->now;
    }

    protected function get(string $key): mixed
    {
        return $this->store[$key] ?? null;
    }

    protected function put(string $key, array $value, int $ttl): void
    {
        $this->store[$key] = $value;
    }
}
