<?php
declare(strict_types=1);

namespace app\service\security;

final class RateLimitExceededException extends \RuntimeException
{
    public function __construct(
        private readonly RateLimitPolicy $policy,
        private readonly string $key,
        private readonly int $retryAfter
    ) {
        parent::__construct('请求过于频繁，请稍后重试');
    }

    public function policy(): RateLimitPolicy
    {
        return $this->policy;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function retryAfter(): int
    {
        return max(1, $this->retryAfter);
    }

    public function maxRequests(): int
    {
        return $this->policy->maxRequests();
    }

    public function windowSeconds(): int
    {
        return $this->policy->windowSeconds();
    }
}
