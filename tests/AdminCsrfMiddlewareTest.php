<?php
declare(strict_types=1);

namespace tests;

use app\middleware\AdminCsrf;
use PHPUnit\Framework\TestCase as BaseTestCase;
use think\App;
use think\Request;

final class AdminCsrfMiddlewareTest extends BaseTestCase
{
    private static App $app;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        self::$app = new App($rootPath);
        self::$app->initialize();
    }

    public function test_admin_csrf_rejects_write_request_without_ajax_header(): void
    {
        $request = $this->request('POST');

        $response = $this->middleware()->handle(
            $request,
            static fn () => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(403, $response->getCode());
        self::assertSame(40301, $payload['code']);
        self::assertSame('CSRF 校验失败', $payload['msg']);
        self::assertNull($payload['data']);
    }

    public function test_admin_csrf_allows_write_request_with_ajax_header(): void
    {
        $request = $this->request('POST', ['X-Requested-With' => 'XMLHttpRequest']);

        $response = $this->middleware()->handle(
            $request,
            static fn () => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getCode());
        self::assertSame(1, $payload['code']);
    }

    public function test_admin_csrf_allows_safe_request_without_ajax_header(): void
    {
        $request = $this->request('GET');

        $response = $this->middleware()->handle(
            $request,
            static fn () => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getCode());
        self::assertSame(1, $payload['code']);
    }

    private function middleware(): AdminCsrf
    {
        if (!class_exists(AdminCsrf::class)) {
            self::fail('Admin CSRF middleware is missing.');
        }

        return new AdminCsrf();
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, array $headers = []): Request
    {
        $request = (clone self::$app->request)
            ->withServer(['REQUEST_METHOD' => strtoupper($method)])
            ->setMethod(strtoupper($method));

        if ($headers !== []) {
            $request = $request->withHeader($headers);
        }

        self::$app->instance('request', $request);

        return $request;
    }
}
