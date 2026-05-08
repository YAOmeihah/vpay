<?php
declare(strict_types=1);

namespace app\service\security;

final class RateLimitPolicy
{
    public function __construct(
        private readonly string $name,
        private readonly int $maxRequests,
        private readonly int $windowSeconds
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function maxRequests(): int
    {
        return max(1, $this->maxRequests);
    }

    public function windowSeconds(): int
    {
        return max(1, $this->windowSeconds);
    }
}
