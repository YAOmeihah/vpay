<?php
declare(strict_types=1);

namespace tests;

use app\controller\monitor\Monitor;
use app\service\CacheService;
use PHPUnit\Framework\TestCase;
use think\App;

class MonitorControllerAppPushTest extends TestCase
{
    private static App $app;
    private static string $rootPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        self::$app = new App(self::$rootPath);
        self::$app->initialize();
    }

    protected function tearDown(): void
    {
        CacheService::clearAll();
        parent::tearDown();
    }

    public function test_app_push_converts_amount_cents_and_calls_order_handler(): void
    {
        $request = (clone self::$app->request)
            ->withPost([
                'type' => '1',
                'amountCents' => '1234',
                'ts' => '1712300000000',
                'nonce' => 'nonce-1',
                'eventId' => 'evt-1',
                'sign' => 'signed',
            ])
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');

        self::$app->instance('request', $request);

        $controller = new class(self::$app) extends Monitor {
            public array $handled = [];

            protected function closeExpiredOrders(): void
            {
            }

            protected function verifyMonitorPushSignature(
                int $type,
                int $amountCents,
                int $ts,
                string $nonce,
                string $eventId,
                string $sign
            ): bool {
                return true;
            }

            protected function validateMonitorReplay(string $eventId, string $nonce, int $timestamp): string
            {
                return 'accepted';
            }

            protected function handlePayPush(string $price, int $type): array
            {
                $this->handled = [$price, $type];
                return ['alreadyProcessed' => false, 'notifyOk' => true];
            }
        };

        $response = $controller->appPush();
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['12.34', 1], $controller->handled);
        $this->assertSame(1, $payload['code']);
    }

    public function test_app_push_returns_idempotent_success_for_duplicate_event_id(): void
    {
        $request = (clone self::$app->request)
            ->withPost([
                'type' => '1',
                'amountCents' => '1234',
                'ts' => '1712300000000',
                'nonce' => 'nonce-dup',
                'eventId' => 'evt-dup',
                'sign' => 'signed',
            ])
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');

        self::$app->instance('request', $request);

        $controller = new class(self::$app) extends Monitor {
            public bool $orderHandlerCalled = false;

            protected function closeExpiredOrders(): void
            {
            }

            protected function verifyMonitorPushSignature(
                int $type,
                int $amountCents,
                int $ts,
                string $nonce,
                string $eventId,
                string $sign
            ): bool {
                return true;
            }

            protected function validateMonitorReplay(string $eventId, string $nonce, int $timestamp): string
            {
                return 'duplicate';
            }

            protected function handlePayPush(string $price, int $type): array
            {
                $this->orderHandlerCalled = true;
                return ['alreadyProcessed' => false, 'notifyOk' => true];
            }
        };

        $response = $controller->appPush();
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse($controller->orderHandlerCalled);
        $this->assertSame(1, $payload['code']);
        $this->assertSame('监控事件已处理', $payload['msg']);
    }

    public function test_app_push_accepts_millisecond_timestamp_payloads(): void
    {
        $request = (clone self::$app->request)
            ->withPost([
                'type' => '2',
                'amountCents' => '10',
                'ts' => '1712300000000',
                'nonce' => 'nonce-ms',
                'eventId' => 'evt-ms',
                'sign' => 'signed',
            ])
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');

        self::$app->instance('request', $request);

        $controller = new class(self::$app) extends Monitor {
            protected function closeExpiredOrders(): void
            {
            }

            protected function verifyMonitorPushSignature(
                int $type,
                int $amountCents,
                int $ts,
                string $nonce,
                string $eventId,
                string $sign
            ): bool {
                return true;
            }

            protected function validateMonitorReplay(string $eventId, string $nonce, int $timestamp): string
            {
                if ($timestamp !== 1712300000000) {
                    throw new \RuntimeException('unexpected timestamp');
                }

                return 'accepted';
            }

            protected function handlePayPush(string $price, int $type): array
            {
                return ['alreadyProcessed' => false, 'notifyOk' => true];
            }
        };

        $response = $controller->appPush();
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $payload['code']);
    }

    public function test_app_push_returns_replay_guard_timestamp_error_response(): void
    {
        $request = (clone self::$app->request)
            ->withPost([
                'type' => '2',
                'amountCents' => '10',
                'ts' => '1712300000',
                'nonce' => 'nonce-seconds',
                'eventId' => 'evt-seconds',
                'sign' => 'signed',
            ])
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');

        self::$app->instance('request', $request);

        $controller = new class(self::$app) extends Monitor {
            protected function closeExpiredOrders(): void
            {
            }

            protected function verifyMonitorPushSignature(
                int $type,
                int $amountCents,
                int $ts,
                string $nonce,
                string $eventId,
                string $sign
            ): bool {
                return true;
            }

            protected function validateMonitorReplay(string $eventId, string $nonce, int $timestamp): string
            {
                throw new \RuntimeException('监控回调时间戳已失效');
            }

            protected function handlePayPush(string $price, int $type): array
            {
                return ['alreadyProcessed' => false, 'notifyOk' => true];
            }
        };

        $response = $controller->appPush();
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(-1, $payload['code']);
        $this->assertSame('监控回调时间戳已失效', $payload['msg']);
    }
}
