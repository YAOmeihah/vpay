<?php
declare(strict_types=1);

namespace app\service\security;

use think\Request;

class RateLimitPolicyResolver
{
    /** @var array<string, string> */
    private const EXACT_PATH_POLICIES = [
        'login' => 'admin_login',
        'createorder' => 'merchant_api',
        'getorder' => 'merchant_api',
        'selectorderpaytype' => 'merchant_api',
        'checkorder' => 'merchant_api',
        'closeorder' => 'merchant_api',
        'appheart' => 'monitor_heartbeat',
        'apppush' => 'monitor_push',
        'getstate' => 'monitor_query',
        'closeendorder' => 'monitor_query',
    ];

    public function resolve(Request $request): RateLimitPolicy
    {
        return $this->policy($this->policyName($request));
    }

    public function policyName(Request $request): string
    {
        $path = $this->normalizePath($request->pathinfo());

        if (str_starts_with($path, 'admin/index/')) {
            return 'admin_api';
        }

        return self::EXACT_PATH_POLICIES[$path] ?? 'default';
    }

    private function policy(string $name): RateLimitPolicy
    {
        $config = config('security.rate_limits.' . $name, []);

        return new RateLimitPolicy(
            $name,
            (int) ($config['max_requests'] ?? 120),
            (int) ($config['window_seconds'] ?? 60)
        );
    }

    private function normalizePath(string $path): string
    {
        return strtolower(trim($path, " \t\n\r\0\x0B/"));
    }
}
