<?php
declare(strict_types=1);

namespace tests;

use app\controller\monitor\Monitor;
use app\model\MonitorTerminal;
use app\service\CacheService;
use app\service\security\MonitorReplayGuard;
use PHPUnit\Framework\TestCase;
use think\App;

class MonitorControllerHeartbeatTest extends TestCase
{
    private static string $rootPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }

    protected function tearDown(): void
    {
        CacheService::clearAll();
        @restore_exception_handler();
        @restore_error_handler();
        parent::tearDown();
    }

    public function test_get_state_rejects_expired_simple_signature_timestamp(): void
    {
        $app = self::makeApp();

        $request = (clone $app->request)
            ->withGet([
                'terminalCode' => 'term-a',
                'ts' => '1712300000',
                'sign' => 'signed',
            ])
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET');

        $app->instance('request', $request);

        $controller = new class($app) extends Monitor {
            protected function resolveTerminal(string $terminalCode): MonitorTerminal
            {
                return new MonitorTerminal([
                    'id' => 11,
                    'terminal_code' => $terminalCode,
                    'terminal_name' => '终端A',
                    'last_heartbeat_at' => 1712300000,
                    'last_paid_at' => 0,
                    'online_state' => 'online',
                ]);
            }

            protected function verifyTerminalMonitorSimpleSignature(string $terminalCode, string $data, string $sign): bool
            {
                return true;
            }

            protected function monitorReplayGuard(): MonitorReplayGuard
            {
                return new MonitorHeartbeatGuardProbe(1712300000000);
            }
        };

        $response = $controller->getState();
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(-1, $payload['code']);
        $this->assertSame('监控回调时间戳已失效', $payload['msg']);
    }

    public function test_app_heart_rejects_replayed_timestamp_for_same_terminal(): void
    {
        $app = self::makeApp();
        $guard = new MonitorHeartbeatGuardProbe(1712300000000);

        $request = (clone $app->request)
            ->withPost([
                'terminalCode' => 'term-a',
                'ts' => '1712300000000',
                'sign' => 'signed',
            ])
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');

        $app->instance('request', $request);

        $controller = new class($app, $guard) extends Monitor {
            public int $heartbeatWrites = 0;

            public function __construct(App $app, private readonly MonitorHeartbeatGuardProbe $guard)
            {
                parent::__construct($app);
            }

            protected function closeExpiredOrders(): void
            {
            }

            protected function resolveTerminal(string $terminalCode): MonitorTerminal
            {
                return new MonitorTerminal([
                    'id' => 11,
                    'terminal_code' => $terminalCode,
                    'terminal_name' => '终端A',
                ]);
            }

            protected function verifyTerminalMonitorSimpleSignature(string $terminalCode, string $data, string $sign): bool
            {
                return true;
            }

            protected function markTerminalHeartbeat(int $terminalId, string $ip): void
            {
                $this->heartbeatWrites++;
            }

            protected function monitorReplayGuard(): MonitorReplayGuard
            {
                return $this->guard;
            }
        };

        $first = json_decode((string) $controller->appHeart()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $second = json_decode((string) $controller->appHeart()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $first['code']);
        $this->assertSame(-1, $second['code']);
        $this->assertSame('监控心跳已重放', $second['msg']);
        $this->assertSame(1, $controller->heartbeatWrites);
    }

    private static function makeApp(): App
    {
        $app = new App(self::$rootPath);
        $app->initialize();

        return $app;
    }
}

final class MonitorHeartbeatGuardProbe extends MonitorReplayGuard
{
    /**
     * @var array<string, mixed>
     */
    private array $store = [];

    public function __construct(private readonly int $now)
    {
    }

    protected function currentTimestamp(): int
    {
        return $this->now;
    }

    protected function getValue(string $key): mixed
    {
        return $this->store[$key] ?? null;
    }

    protected function putValue(string $key, mixed $value, int $ttl): void
    {
        $this->store[$key] = ['value' => $value, 'ttl' => $ttl];
    }
}
