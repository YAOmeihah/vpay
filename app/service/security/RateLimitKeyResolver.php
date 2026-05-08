<?php
declare(strict_types=1);

namespace app\service\security;

use think\facade\Session;
use think\Request;

class RateLimitKeyResolver
{
    public function resolve(RateLimitPolicy $policy, Request $request): string
    {
        $name = $policy->name();

        if ($name === 'admin_api') {
            $adminUser = trim((string) Session::get('admin_user', ''));
            if ($adminUser !== '') {
                return 'admin:' . $adminUser;
            }
        }

        if ($name === 'merchant_api') {
            foreach (['pid', 'merchantId', 'merchant_id', 'appId', 'app_id'] as $paramName) {
                $value = trim((string) $request->param($paramName, ''));
                if ($value !== '') {
                    return 'merchant:' . $value;
                }
            }
        }

        if (str_starts_with($name, 'monitor_')) {
            $terminalCode = trim((string) $request->param('terminalCode', ''));
            if ($terminalCode !== '') {
                return 'terminal:' . $terminalCode;
            }
        }

        return 'ip:' . (string) $request->ip();
    }
}
