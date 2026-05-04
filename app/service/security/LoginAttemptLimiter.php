<?php
declare(strict_types=1);

namespace app\service\security;

class LoginAttemptLimiter
{
    private const LOGIN_PREFIX = 'login_attempts_';
    private const RATE_LIMIT_PREFIX = 'rate_limit_';
    private const LOGIN_TTL = 300;
    private const RATE_LIMIT_TTL = 60;
    private const LOGIN_THRESHOLD = 5;
    private const RATE_LIMIT_THRESHOLD = 120;

    public function tooManyLoginAttempts(string $clientIp): bool
    {
        return $this->attemptsFor(self::LOGIN_PREFIX, $clientIp) >= $this->loginThreshold();
    }

    public function recordLoginFailure(string $clientIp): int
    {
        return $this->increment(self::LOGIN_PREFIX, $clientIp, $this->loginLockoutTtl());
    }

    public function clearLoginAttempts(string $clientIp): void
    {
        $this->forget($this->cacheKey(self::LOGIN_PREFIX, $clientIp));
    }

    public function tooManyRequests(string $clientIp): bool
    {
        return $this->attemptsFor(self::RATE_LIMIT_PREFIX, $clientIp) >= $this->rateLimitThreshold();
    }

    public function recordRequest(string $clientIp): int
    {
        return $this->increment(self::RATE_LIMIT_PREFIX, $clientIp, $this->rateLimitWindow());
    }

    protected function get(string $key): mixed
    {
        return cache($key);
    }

    protected function put(string $key, int $value, int $ttl): void
    {
        cache($key, $value, $ttl);
    }

    protected function forget(string $key): void
    {
        cache($key, null);
    }

    protected function loginThreshold(): int
    {
        return (int) config('security.login.max_attempts', self::LOGIN_THRESHOLD);
    }

    protected function loginLockoutTtl(): int
    {
        return (int) config('security.login.lockout_time', self::LOGIN_TTL);
    }

    protected function rateLimitThreshold(): int
    {
        return max(1, (int) config('security.rate_limit.max_requests', self::RATE_LIMIT_THRESHOLD));
    }

    protected function rateLimitWindow(): int
    {
        return max(1, (int) config('security.rate_limit.window_seconds', self::RATE_LIMIT_TTL));
    }

    private function attemptsFor(string $prefix, string $clientIp): int
    {
        $value = $this->get($this->cacheKey($prefix, $clientIp));
        return is_numeric($value) ? (int) $value : 0;
    }

    private function increment(string $prefix, string $clientIp, int $ttl): int
    {
        $key = $this->cacheKey($prefix, $clientIp);
        $attempts = $this->attemptsFor($prefix, $clientIp) + 1;
        $this->put($key, $attempts, $ttl);

        return $attempts;
    }

    private function cacheKey(string $prefix, string $clientIp): string
    {
        return $prefix . md5($clientIp);
    }
}
