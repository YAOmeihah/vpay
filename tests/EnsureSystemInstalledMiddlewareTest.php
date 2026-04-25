<?php
declare(strict_types=1);

namespace tests;

use app\middleware\EnsureSystemInstalled;

final class EnsureSystemInstalledMiddlewareTest extends TestCase
{
    public function test_returns_json_error_for_admin_api_when_system_is_not_installed(): void
    {
        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getMain');

        $this->app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $response = $middleware->handle(
            $request,
            static fn ($nextRequest) => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getCode());
        self::assertSame(50301, $payload['code']);
        self::assertSame('系统尚未安装', $payload['msg']);
        self::assertSame('/install', $payload['data']['installUrl']);
    }

    public function test_redirects_html_request_to_installer_when_upgrade_is_required(): void
    {
        $request = (clone $this->app->request)
            ->withServer([
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->setMethod('GET')
            ->setPathinfo('console');

        $this->app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'upgrade_required', 'message' => '系统待升级'];
            }
        };

        $response = $middleware->handle(
            $request,
            static function () {
                self::fail('HTML requests must be redirected before reaching the controller');
            }
        );

        self::assertSame(302, $response->getCode());
        self::assertSame('/install', $response->getHeader('Location'));
    }

    public function test_recovery_state_points_json_clients_to_recovery_page(): void
    {
        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getMain');

        $this->app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'recovery_required', 'message' => '系统需要恢复'];
            }
        };

        $response = $middleware->handle(
            $request,
            static fn ($nextRequest) => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getCode());
        self::assertSame(50304, $payload['code']);
        self::assertSame('/install/recover', $payload['data']['installUrl']);
    }

    public function test_returns_maintenance_error_for_regular_api_while_update_is_running(): void
    {
        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getMain');

        $this->app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'installed', 'message' => '系统已安装'];
            }

            protected function hasUpdateLock(): bool
            {
                return true;
            }
        };

        $response = $middleware->handle(
            $request,
            static fn ($nextRequest) => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getCode());
        self::assertSame(50305, $payload['code']);
        self::assertSame('系统正在更新，请稍后再试', $payload['msg']);
    }

    public function test_allows_update_status_api_while_update_is_running(): void
    {
        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getUpdateStatus');

        $this->app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'installed', 'message' => '系统已安装'];
            }

            protected function hasUpdateLock(): bool
            {
                return true;
            }
        };

        $response = $middleware->handle(
            $request,
            static fn ($nextRequest) => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getCode());
        self::assertSame(1, $payload['code']);
    }
}
