<?php
declare(strict_types=1);

namespace app\service\install;

use think\Request;

class InstallGuardService
{
    public function shouldBypass(Request $request): bool
    {
        $path = ltrim($request->pathinfo(), '/');

        return $path === ''
            || $path === 'install'
            || str_starts_with($path, 'install/')
            || str_starts_with($path, 'payment-test/');
    }

    public function shouldBlock(string $state): bool
    {
        return in_array($state, ['not_installed', 'upgrade_required', 'locked', 'recovery_required'], true);
    }

    public function errorPayload(string $state): array
    {
        return match ($state) {
            'not_installed' => ['code' => 50301, 'msg' => '系统尚未安装'],
            'upgrade_required' => ['code' => 50302, 'msg' => '系统待升级'],
            'locked' => ['code' => 50303, 'msg' => '安装或升级正在执行'],
            default => ['code' => 50304, 'msg' => '系统需要恢复'],
        };
    }
}
