<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\install\InstallGuardService;
use app\service\install\InstallStateService;
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
}
