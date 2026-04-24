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
}
