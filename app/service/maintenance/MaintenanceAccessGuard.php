<?php
declare(strict_types=1);

namespace app\service\maintenance;

use think\Request;

class MaintenanceAccessGuard
{
    public const TOKEN_HEADER = 'X-Maintenance-Token';

    public function assertAllowed(Request $request): void
    {
        if (!$this->config()->isEnabled()) {
            throw new MaintenanceAccessDeniedException('维护接口未启用', 40303);
        }

        $this->assertIpAllowed($request);
        $this->assertTokenAllowed($request);
    }

    private function assertIpAllowed(Request $request): void
    {
        $clientIp = trim((string) $request->server('REMOTE_ADDR', ''));
        if ($clientIp === '') {
            $clientIp = (string) $request->ip();
        }
        $allowedIps = $this->allowedIps();

        if ($allowedIps === [] || !in_array($clientIp, $allowedIps, true)) {
            throw new MaintenanceAccessDeniedException('维护接口 IP 不允许', 40302);
        }
    }

    private function assertTokenAllowed(Request $request): void
    {
        $expected = $this->config()->token();
        $actual = (string) $request->header(self::TOKEN_HEADER, '');

        if ($expected === '' || $actual === '' || !hash_equals($expected, $actual)) {
            throw new MaintenanceAccessDeniedException('维护接口认证失败', 40301);
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedIps(): array
    {
        return $this->config()->allowedIps();
    }

    private function config(): MaintenanceConfig
    {
        return app()->make(MaintenanceConfig::class);
    }
}
