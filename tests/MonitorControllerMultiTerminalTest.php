<?php
declare(strict_types=1);

namespace tests;

use app\controller\monitor\Monitor;
use app\model\MonitorTerminal;
use think\App;

class MonitorControllerMultiTerminalTest extends TestCase
{
    private static App $localApp;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$localApp = self::$sharedApp;
    }

    public function test_app_push_routes_terminal_code_into_signature_and_order_matching(): void
    {
        $request = (clone self::$localApp->request)
            ->withPost([
                'terminalCode' => 'term-a',
                'type' => '1',
                'amountCents' => '1234',
                'ts' => '1712300000000',
                'nonce' => 'nonce-term-a',
                'eventId' => 'evt-term-a',
                'sign' => 'signed',
            ])
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');

        self::$localApp->instance('request', $request);

        $terminal = new MonitorTerminal([
            'id' => 11,
            'terminal_code' => 'term-a',
            'terminal_name' => '终端 A',
        ]);

        $controller = new class(self::$localApp, $terminal) extends Monitor {
            public array $verified = [];
            public array $handled = [];

            public function __construct(App $app, private readonly MonitorTerminal $terminal)
            {
                parent::__construct($app);
            }

            protected function closeExpiredOrders(): void
            {
            }

            protected function resolveTerminal(string $terminalCode): MonitorTerminal
            {
                return $this->terminal;
            }

            protected function verifyTerminalMonitorPushSignature(
                string $terminalCode,
                int $type,
                int $amountCents,
                int $ts,
                string $nonce,
                string $eventId,
                string $sign
            ): bool {
                $this->verified = [$terminalCode, $type, $amountCents, $ts, $nonce, $eventId, $sign];
                return true;
            }

            protected function validateMonitorReplay(
                string $eventId,
                string $nonce,
                int $timestamp,
                ?string $scope = null
            ): string
            {
                return 'accepted';
            }

            protected function handleTerminalPayPush(
                int $terminalId,
                string $price,
                int $type,
                string $eventId,
                array $rawPayload
            ): array {
                $this->handled = [$terminalId, $price, $type, $eventId, $rawPayload['terminalCode'] ?? ''];
                return ['alreadyProcessed' => false, 'notifyOk' => true];
            }
        };

        $response = $controller->appPush();
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            ['term-a', 1, 1234, 1712300000000, 'nonce-term-a', 'evt-term-a', 'signed'],
            $controller->verified
        );
        $this->assertSame([11, '12.34', 1, 'evt-term-a', 'term-a'], $controller->handled);
        $this->assertSame(1, $payload['code']);
    }
}
