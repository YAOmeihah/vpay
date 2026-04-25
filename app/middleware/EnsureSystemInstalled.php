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

        $stateName = (string) ($state['state'] ?? 'installed');
        $installUrl = $guard->installUrl($stateName);
        if (!$this->shouldReturnJson($request)) {
            return response('', 302, ['Location' => $installUrl]);
        }

        $payload = $guard->errorPayload($stateName);

        return json([
            'code' => $payload['code'],
            'msg' => $payload['msg'],
            'data' => ['installUrl' => $installUrl],
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

    private function shouldReturnJson(Request $request): bool
    {
        $path = ltrim($request->pathinfo(), '/');
        if (
            str_starts_with($path, 'admin/index/')
            || str_starts_with($path, 'merchant/')
            || str_starts_with($path, 'monitor/')
            || in_array($path, ['login', 'enQrcode'], true)
        ) {
            return true;
        }

        $requestedWith = strtolower((string) $request->header('X-Requested-With'));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept'));

        return str_contains($accept, 'application/json');
    }
}
