<?php
declare(strict_types=1);

namespace tests;

use app\controller\Admin;
use app\middleware\AdminAuth;
use think\facade\Session;

final class AdminAuthMiddlewareTest extends TestCase
{
    public function test_admin_auth_returns_http_401_with_auth_error_code_when_session_is_missing(): void
    {
        Session::clear();

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET');

        $this->app->instance('request', $request);

        $middleware = new AdminAuth();
        $response = $middleware->handle(
            $request,
            static fn ($nextRequest) => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getCode());
        self::assertSame(40101, $payload['code']);
        self::assertSame('没有登录', $payload['msg']);
        self::assertNull($payload['data']);
    }

    public function test_profile_uses_same_auth_error_semantics_when_session_is_missing(): void
    {
        Session::clear();

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET');

        $this->app->instance('request', $request);

        $controller = new Admin($this->app);
        $response = $controller->profile();
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getCode());
        self::assertSame(40101, $payload['code']);
        self::assertSame('没有登录', $payload['msg']);
        self::assertNull($payload['data']);
    }
}
