<?php
declare(strict_types=1);

namespace tests;

use app\ExceptionHandle;

final class AdminExceptionHandleTest extends TestCase
{
    public function test_admin_api_runtime_exception_renders_json_message_for_management_console(): void
    {
        $request = (clone $this->app->request)
            ->withServer([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/admin/index/saveTerminal',
            ])
            ->withHeader([
                'Accept' => 'application/json, text/plain, */*',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->setMethod('POST')
            ->setPathinfo('admin/index/saveTerminal');

        $handler = new ExceptionHandle($this->app);
        $response = $handler->render(
            $request,
            new \RuntimeException('终端编码和终端名称不能为空')
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getCode());
        self::assertSame(0, $payload['code']);
        self::assertSame('终端编码和终端名称不能为空', $payload['msg']);
        self::assertNull($payload['data']);
    }
}
