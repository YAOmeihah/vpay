<?php
declare(strict_types=1);

namespace tests;

use app\middleware\EnsureSystemInstalled;
use PHPUnit\Framework\TestCase as BaseTestCase;
use think\App;

final class EnsureSystemInstalledMiddlewareTest extends BaseTestCase
{
    private static App $app;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        self::$app = new App($rootPath);
        self::$app->initialize();
    }

    public function test_returns_json_error_for_admin_api_when_system_is_not_installed(): void
    {
        $request = (clone self::$app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getMain');

        self::$app->instance('request', $request);

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

    public function test_returns_json_error_for_top_level_merchant_api_when_system_is_not_installed(): void
    {
        $request = (clone self::$app->request)
            ->withServer([
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->setMethod('GET')
            ->setPathinfo('createOrder');

        self::$app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $response = $middleware->handle(
            $request,
            static function () {
                self::fail('Merchant API requests must be rejected before reaching the controller');
            }
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getCode());
        self::assertSame(50301, $payload['code']);
        self::assertSame('系统尚未安装', $payload['msg']);
        self::assertSame('/install', $payload['data']['installUrl']);
    }

    public function test_returns_payment_error_page_for_html_create_order_when_system_is_not_installed(): void
    {
        self::$app->view->forgetDriver();

        $request = (clone self::$app->request)
            ->withServer([
                'REQUEST_METHOD' => 'POST',
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->withPost([
                'isHtml' => '1',
            ])
            ->setMethod('POST')
            ->setPathinfo('createOrder');

        self::$app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $response = $middleware->handle(
            $request,
            static function () {
                self::fail('HTML createOrder requests must be rejected before reaching the controller');
            }
        );

        $html = (string) $response->getContent();

        self::assertSame(503, $response->getCode());
        self::assertStringContainsString('payment-error-shell', $html);
        self::assertStringContainsString('支付服务暂不可用', $html);
        self::assertStringNotContainsString('/install', $html);
    }

    public function test_returns_json_error_for_top_level_monitor_api_when_system_is_not_installed(): void
    {
        $request = (clone self::$app->request)
            ->withServer([
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->setMethod('GET')
            ->setPathinfo('appHeart');

        self::$app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $response = $middleware->handle(
            $request,
            static function () {
                self::fail('Monitor API requests must be rejected before reaching the controller');
            }
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getCode());
        self::assertSame(50301, $payload['code']);
        self::assertSame('系统尚未安装', $payload['msg']);
        self::assertSame('/install', $payload['data']['installUrl']);
    }

    public function test_payment_test_callback_does_not_bypass_install_guard(): void
    {
        $request = (clone self::$app->request)
            ->withServer([
                'REQUEST_METHOD' => 'POST',
                'HTTP_ACCEPT' => '*/*',
            ])
            ->setMethod('POST')
            ->setPathinfo('payment-test/notify');

        self::$app->instance('request', $request);

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $response = $middleware->handle(
            $request,
            static function () {
                self::fail('Payment test callbacks must not bypass install guard');
            }
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getCode());
        self::assertSame(50301, $payload['code']);
    }

    public function test_redirects_html_request_to_installer_when_upgrade_is_required(): void
    {
        $request = (clone self::$app->request)
            ->withServer([
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->setMethod('GET')
            ->setPathinfo('console');

        self::$app->instance('request', $request);

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

    public function test_legacy_recovery_state_points_json_clients_to_installer(): void
    {
        $request = (clone self::$app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getMain');

        self::$app->instance('request', $request);

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
        self::assertSame('/install', $payload['data']['installUrl']);
    }

    public function test_returns_maintenance_error_for_regular_api_while_update_is_running(): void
    {
        $request = (clone self::$app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getMain');

        self::$app->instance('request', $request);

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

    public function test_returns_payment_error_page_for_html_create_order_while_update_is_running(): void
    {
        self::$app->view->forgetDriver();

        $request = (clone self::$app->request)
            ->withServer([
                'REQUEST_METHOD' => 'POST',
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->withPost([
                'isHtml' => '1',
            ])
            ->setMethod('POST')
            ->setPathinfo('createOrder');

        self::$app->instance('request', $request);

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
            static function () {
                self::fail('HTML createOrder requests must be rejected before reaching the controller during update lock');
            }
        );

        $html = (string) $response->getContent();

        self::assertSame(503, $response->getCode());
        self::assertStringContainsString('payment-error-shell', $html);
        self::assertStringContainsString('支付服务暂不可用', $html);
        self::assertStringNotContainsString('系统正在更新，请稍后再试', $html);
    }

    public function test_allows_update_status_api_while_update_is_running(): void
    {
        $request = (clone self::$app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getUpdateStatus');

        self::$app->instance('request', $request);

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
