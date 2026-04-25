<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\install\InstallGuardService;
use app\service\install\InstallStateService;
use app\service\update\UpdateStateStore;
use Closure;
use think\Request;
use think\Response;

class EnsureSystemInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = new InstallGuardService();
        if ($guard->shouldBypass($request)) {
            return $next($request);
        }

        $state = $this->installState();
        if (!$guard->shouldBlock((string) ($state['state'] ?? 'installed'))) {
            if ($this->hasUpdateLock() && !$this->shouldAllowDuringUpdate($request)) {
                return json([
                    'code' => 50305,
                    'msg' => '系统正在更新，请稍后再试',
                    'data' => null,
                ], 503);
            }

            return $next($request);
        }

        $payload = $guard->errorPayload((string) $state['state']);

        return json([
            'code' => $payload['code'],
            'msg' => $payload['msg'],
            'data' => ['installUrl' => '/install'],
        ], 503);
    }

    protected function installState(): array
    {
        return app()->make(InstallStateService::class)->status();
    }

    protected function hasUpdateLock(): bool
    {
        return app()->make(UpdateStateStore::class)->hasLock();
    }

    private function shouldAllowDuringUpdate(Request $request): bool
    {
        $path = ltrim($request->pathinfo(), '/');

        return in_array($path, [
            'admin/index/getUpdateStatus',
            'admin/index/getUpdateRecovery',
        ], true);
    }
}
