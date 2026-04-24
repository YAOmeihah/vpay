<?php
declare(strict_types=1);

namespace tests;

use app\controller\Index;
use think\Response;

final class RootEntryControllerTest extends TestCase
{
    public function test_root_redirects_to_installer_when_system_is_not_installed(): void
    {
        $controller = new class($this->app) extends Index {
            protected function installState(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $response = $controller->index();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->getCode());
        self::assertSame('/install', $response->getHeader('Location'));
    }

    public function test_root_redirects_to_recovery_when_install_state_requires_recovery(): void
    {
        $controller = new class($this->app) extends Index {
            protected function installState(): array
            {
                return ['state' => 'recovery_required', 'message' => '安装或升级失败，等待恢复'];
            }
        };

        $response = $controller->index();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->getCode());
        self::assertSame('/install/recover', $response->getHeader('Location'));
    }

    public function test_root_serves_static_portal_when_system_is_installed(): void
    {
        $controller = new class($this->app) extends Index {
            protected function installState(): array
            {
                return ['state' => 'installed', 'message' => '系统已安装'];
            }
        };

        $response = $controller->index();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getCode());
        self::assertStringContainsString('支付处理与后台协同平台', $response->getContent());
        self::assertStringContainsString('text/html', (string) $response->getHeader('Content-Type'));
    }
}
