<?php
declare(strict_types=1);

namespace app\service\security;

class RequestRateLimiter
{
    public function assertAllowed(RateLimitPolicy $policy, string $key): void
    {
        $cacheKey = $this->cacheKey($policy, $key);
        $now = $this->now();
        $bucket = $this->normalizeBucket($this->get($cacheKey), $policy, $now);

        if ($bucket['reset_at'] <= $now) {
            $bucket = [
                'count' => 0,
                'reset_at' => $now + $policy->windowSeconds(),
            ];
        }

        if ($bucket['count'] >= $policy->maxRequests()) {
            throw new RateLimitExceededException(
                $policy,
                $key,
                max(1, $bucket['reset_at'] - $now)
            );
        }

        $bucket['count']++;
        $this->put($cacheKey, $bucket, max(1, $bucket['reset_at'] - $now));
    }

    protected function now(): int
    {
        return time();
    }

    protected function get(string $key): mixed
    {
        return cache($key);
    }

    /**
     * @param array{count:int, reset_at:int} $value
     */
    protected function put(string $key, array $value, int $ttl): void
    {
        cache($key, $value, $ttl);
    }

    /**
     * @return array{count:int, reset_at:int}
     */
    private function normalizeBucket(mixed $value, RateLimitPolicy $policy, int $now): array
    {
        if (is_array($value) && isset($value['count'], $value['reset_at'])) {
            return [
                'count' => max(0, (int) $value['count']),
                'reset_at' => max($now + 1, (int) $value['reset_at']),
            ];
        }

        return [
            'count' => 0,
            'reset_at' => $now + $policy->windowSeconds(),
        ];
    }

    private function cacheKey(RateLimitPolicy $policy, string $key): string
    {
        return 'rate_limit:' . $policy->name() . ':' . sha1($key);
    }
}
