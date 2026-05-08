<?php
declare(strict_types=1);

namespace tests;

use app\middleware\Security;
use app\service\security\RateLimitKeyResolver;
use app\service\security\RateLimitPolicy;
use app\service\security\RateLimitPolicyResolver;
use app\service\security\RequestRateLimiter;
use PHPUnit\Framework\TestCase;
use think\Request;

final class SecurityMiddlewareRateLimitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $app = new \think\App(dirname(__DIR__) . DIRECTORY_SEPARATOR);
        $app->initialize();
    }

    public function test_security_middleware_returns_429_with_retry_after_when_limited(): void
    {
        $middleware = new SecurityMiddlewareProbe(
            new FixedPolicyResolver(new RateLimitPolicy('default', 1, 60)),
            new FixedKeyResolver('ip:127.0.0.1'),
            new SecurityRequestRateLimiterProbe(1000)
        );
        $request = $this->requestForPath('unknown');

        $first = $middleware->handle($request, fn () => response('ok'));
        $second = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(200, $first->getCode());
        $this->assertSame(429, $second->getCode());
        $this->assertSame('60', $second->getHeader('Retry-After'));
        $this->assertStringContainsString('请求过于频繁', (string) $second->getContent());
    }

    public function test_monitor_terminal_keys_do_not_share_same_ip_bucket(): void
    {
        $limiter = new SecurityRequestRateLimiterProbe(1000);
        $middleware = new SecurityMiddlewareProbe(
            new FixedPolicyResolver(new RateLimitPolicy('monitor_heartbeat', 1, 60)),
            new RateLimitKeyResolver(),
            $limiter
        );

        $first = $middleware->handle(
            $this->requestForPath('appHeart', ['terminalCode' => 'T1'], '10.0.0.1'),
            fn () => response('ok')
        );
        $second = $middleware->handle(
            $this->requestForPath('appHeart', ['terminalCode' => 'T2'], '10.0.0.1'),
            fn () => response('ok')
        );

        $this->assertSame(200, $first->getCode());
        $this->assertSame(200, $second->getCode());
    }

    public function test_monitor_heartbeat_limit_does_not_consume_monitor_push_limit(): void
    {
        $limiter = new SecurityRequestRateLimiterProbe(1000);
        $middleware = new SecurityMiddlewareProbe(
            new class extends RateLimitPolicyResolver {
                public function resolve(Request $request): RateLimitPolicy
                {
                    return match ($request->pathinfo()) {
                        'appHeart' => new RateLimitPolicy('monitor_heartbeat', 1, 60),
                        'appPush' => new RateLimitPolicy('monitor_push', 1, 60),
                        default => new RateLimitPolicy('default', 1, 60),
                    };
                }
            },
            new RateLimitKeyResolver(),
            $limiter
        );

        $heartbeat = $middleware->handle(
            $this->requestForPath('appHeart', ['terminalCode' => 'T1'], '10.0.0.1'),
            fn () => response('heart')
        );
        $push = $middleware->handle(
            $this->requestForPath('appPush', ['terminalCode' => 'T1'], '10.0.0.1'),
            fn () => response('push')
        );

        $this->assertSame(200, $heartbeat->getCode());
        $this->assertSame(200, $push->getCode());
    }

    private function requestForPath(string $path, array $params = [], string $ip = '127.0.0.1'): Request
    {
        $request = new Request();
        $request->setPathinfo($path);
        $request->withServer(['REMOTE_ADDR' => $ip]);
        $request->withGet($params);
        $request->withPost($params);
        return $request;
    }
}

final class SecurityMiddlewareProbe extends Security
{
    public function __construct(
        private readonly RateLimitPolicyResolver $policyResolver,
        private readonly RateLimitKeyResolver $keyResolver,
        private readonly RequestRateLimiter $requestLimiter
    ) {
    }

    protected function rateLimitPolicyResolver(): RateLimitPolicyResolver
    {
        return $this->policyResolver;
    }

    protected function rateLimitKeyResolver(): RateLimitKeyResolver
    {
        return $this->keyResolver;
    }

    protected function requestRateLimiter(): RequestRateLimiter
    {
        return $this->requestLimiter;
    }
}

final class FixedPolicyResolver extends RateLimitPolicyResolver
{
    public function __construct(private readonly RateLimitPolicy $policy)
    {
    }

    public function resolve(Request $request): RateLimitPolicy
    {
        return $this->policy;
    }
}

final class FixedKeyResolver extends RateLimitKeyResolver
{
    public function __construct(private readonly string $key)
    {
    }

    public function resolve(RateLimitPolicy $policy, Request $request): string
    {
        return $this->key;
    }
}

final class SecurityRequestRateLimiterProbe extends RequestRateLimiter
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
