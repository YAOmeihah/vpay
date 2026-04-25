<?php
declare(strict_types=1);

namespace app\service {

    if (!function_exists(__NAMESPACE__ . '\curl_init')) {
        function curl_init(...$args): mixed
        {
            return \tests\NotifyHttpProbe::curlInit(...$args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_setopt')) {
        function curl_setopt(mixed $handle, int $option, mixed $value): bool
        {
            return \tests\NotifyHttpProbe::curlSetOpt($handle, $option, $value);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_exec')) {
        function curl_exec(mixed $handle): string|false
        {
            return \tests\NotifyHttpProbe::curlExec($handle);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_close')) {
        function curl_close(mixed $handle): void
        {
            \tests\NotifyHttpProbe::curlClose($handle);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_error')) {
        function curl_error(mixed $handle): string
        {
            return \tests\NotifyHttpProbe::curlError($handle);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_errno')) {
        function curl_errno(mixed $handle): int
        {
            return \tests\NotifyHttpProbe::curlErrno($handle);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_getinfo')) {
        function curl_getinfo(mixed $handle, ?int $option = null): mixed
        {
            return \tests\NotifyHttpProbe::curlGetInfo($handle, $option);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\gethostbyname')) {
        function gethostbyname(string $host): string
        {
            return \tests\NotifyHttpProbe::gethostbyname($host);
        }
    }
}

namespace app\model {

    if (!class_exists(__NAMESPACE__ . '\PayOrder', false)) {
        /**
         * Minimal in-memory replacement for the Think ORM model used by OrderService.
         * Only the methods exercised by this test suite are implemented.
         */
        class PayOrder
        {
            public const STATE_UNPAID = 0;
            public const STATE_PAID = 1;
            public const STATE_NOTIFY_FAILED = 2;
            public const STATE_EXPIRED = -1;

            public const ASSIGN_STATUS_ASSIGNED = 'assigned';
            public const ASSIGN_STATUS_PENDING_CHOICE = 'pending_choice';

            public const TYPE_WECHAT = 1;
            public const TYPE_ALIPAY = 2;

            /**
             * @var array<int, array<string, mixed>>
             */
            private static array $rows = [];

            private static int $nextId = 1;

            public static function reset(): void
            {
                self::$rows = [];
                self::$nextId = 1;
            }

            /**
             * @param array<string, mixed> $row
             */
            public static function seed(array $row): int
            {
                $id = (int) ($row['id'] ?? self::$nextId++);
                $row['id'] = $id;
                self::$rows[$id] = $row;
                return $id;
            }

            public static function where(string $field, mixed $value): PayOrderQuery
            {
                return (new PayOrderQuery())->where($field, $value);
            }

            /**
             * @param array<string, mixed> $data
             */
            public static function create(array $data): FakePayOrderRow
            {
                $id = self::$nextId++;
                $data['id'] = $id;
                self::$rows[$id] = $data;
                return new FakePayOrderRow($data);
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            public static function allRows(): array
            {
                return self::$rows;
            }

            /**
             * @param array<int, array<string, mixed>> $rows
             */
            public static function replaceRows(array $rows): void
            {
                self::$rows = $rows;
            }
        }

        final class PayOrderQuery
        {
            /**
             * @var array<string, mixed>
             */
            private array $wheres = [];

            public function where(string $field, mixed $value): self
            {
                $this->wheres[$field] = $value;
                return $this;
            }

            public function lock(bool|string $lock = false): self
            {
                return $this;
            }

            public function find(): ?FakePayOrderRow
            {
                foreach (PayOrder::allRows() as $row) {
                    if ($this->matches($row)) {
                        return new FakePayOrderRow($row);
                    }
                }

                return null;
            }

            public function findOrFail(): FakePayOrderRow
            {
                $row = $this->find();
                if ($row === null) {
                    throw new \RuntimeException('record not found');
                }

                return $row;
            }

            /**
             * @param array<string, mixed> $data
             */
            public function update(array $data): int
            {
                $rows = PayOrder::allRows();
                $affected = 0;

                foreach ($rows as $id => $row) {
                    if (!$this->matches($row)) {
                        continue;
                    }

                    $rows[$id] = array_merge($row, $data);
                    $affected++;
                }

                PayOrder::replaceRows($rows);
                return $affected;
            }

            /**
             * @param array<string, mixed> $row
             */
            private function matches(array $row): bool
            {
                foreach ($this->wheres as $field => $expected) {
                    if (!array_key_exists($field, $row)) {
                        return false;
                    }

                    if ($row[$field] !== $expected) {
                        return false;
                    }
                }

                return true;
            }
        }

        final class FakePayOrderRow implements \ArrayAccess
        {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(private array $data)
            {
            }

            public function offsetExists(mixed $offset): bool
            {
                return array_key_exists((string) $offset, $this->data);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return $this->data[(string) $offset] ?? null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                $this->data[(string) $offset] = $value;
            }

            public function offsetUnset(mixed $offset): void
            {
                unset($this->data[(string) $offset]);
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return $this->data;
            }

            public function getAttr(string $key): mixed
            {
                return $this->data[$key] ?? null;
            }
        }
    }

    if (!class_exists(__NAMESPACE__ . '\TmpPrice', false)) {
        class TmpPrice
        {
            /**
             * @var array<int, array{oid: string, price?: string, channel_id?: int|null}>
             */
            private static array $rows = [];

            private static int $nextId = 1;

            public static bool $throwOnDelete = false;

            public static function reset(): void
            {
                self::$rows = [];
                self::$nextId = 1;
                self::$throwOnDelete = false;
            }

            public static function seed(string $oid, string $price = '', ?int $channelId = null): int
            {
                $id = self::$nextId++;
                self::$rows[$id] = ['oid' => $oid, 'price' => $price, 'channel_id' => $channelId];
                return $id;
            }

            /**
             * @param array<string, mixed> $data
             */
            public static function create(array $data): void
            {
                $channelId = array_key_exists('channel_id', $data) ? (int) $data['channel_id'] : null;
                $price = (string) ($data['price'] ?? '');

                foreach (self::$rows as $row) {
                    if (($row['channel_id'] ?? null) === $channelId && (string) ($row['price'] ?? '') === $price) {
                        throw new \RuntimeException('Duplicate channel price');
                    }
                }

                self::seed((string) ($data['oid'] ?? ''), $price, $channelId);
            }

            public static function where(string $field, mixed $value): TmpPriceQuery
            {
                return (new TmpPriceQuery())->where($field, $value);
            }

            /**
             * @return array<int, array{oid: string, price?: string, channel_id?: int|null}>
             */
            public static function allRows(): array
            {
                return self::$rows;
            }

            /**
             * @param array<int, array{oid: string, price?: string, channel_id?: int|null}> $rows
             */
            public static function replaceRows(array $rows): void
            {
                self::$rows = $rows;
            }
        }

        final class TmpPriceQuery
        {
            /**
             * @var array<string, mixed>
             */
            private array $wheres = [];

            public function where(string $field, mixed $value): self
            {
                $this->wheres[$field] = $value;
                return $this;
            }

            public function delete(): int
            {
                if (TmpPrice::$throwOnDelete) {
                    throw new \RuntimeException('tmp price delete failed');
                }

                $rows = TmpPrice::allRows();
                $affected = 0;

                foreach ($rows as $id => $row) {
                    if (!$this->matches($row)) {
                        continue;
                    }

                    unset($rows[$id]);
                    $affected++;
                }

                TmpPrice::replaceRows($rows);
                return $affected;
            }

            public function count(): int
            {
                $count = 0;

                foreach (TmpPrice::allRows() as $row) {
                    if ($this->matches($row)) {
                        $count++;
                    }
                }

                return $count;
            }

            /**
             * @param array{oid: string, price?: string, channel_id?: int|null} $row
             */
            private function matches(array $row): bool
            {
                foreach ($this->wheres as $field => $expected) {
                    if (!array_key_exists($field, $row)) {
                        return false;
                    }

                    if ($row[$field] !== $expected) {
                        return false;
                    }
                }

                return true;
            }
        }
    }
}

namespace tests {

    use app\model\PayOrder;
    use app\service\OrderCreationKernel;
    use app\service\OrderService;
    use app\service\MonitorService;
    use app\service\NotifyService;
    use app\service\SignService;
    use app\service\cache\OrderCache;
    use app\service\config\SystemConfig;
    use app\service\order\OrderPayloadFactory;
    use app\service\terminal\TerminalCredentialService;
    use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
    use PHPUnit\Framework\TestCase as BaseTestCase;

    #[RunClassInSeparateProcess]
    class CoreServiceRegressionTest extends BaseTestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            if (!class_exists(\app\model\Setting::class, false)) {
                class_alias(FakeSetting::class, \app\model\Setting::class);
            }

            FakeSetting::reset();
        }

        protected function tearDown(): void
        {
            FakeSetting::reset();
            NotifyHttpProbe::reset();
            SignServiceAdapterProbe::$config = null;
            SignServiceAdapterProbe::$credentials = null;
            MonitorServiceAdapterProbe::$now = null;
            MonitorServiceAdapterProbe::$terminalHeartbeatWrites = [];
            NotifyServiceAdapterProbe::$config = null;
            OrderCreationKernelProbe::$config = null;
            OrderCreationKernelProbe::$cachedOrders = [];

            parent::tearDown();
        }

        public function test_sign_service_keeps_md5_contract_while_reading_key_from_config_adapter(): void
        {
            $this->seedSettings(['key' => 'db-sign-key']);

            SignServiceAdapterProbe::$config = new FakeSystemConfig(signKey: 'adapter-sign-key');

            $orderSign = SignServiceAdapterProbe::makeOrderSign(
                'merchant-001',
                'attach',
                1,
                '12.34',
                '12.35'
            );

            $this->assertSame(
                md5('merchant-001' . 'attach' . 1 . '12.34' . '12.35' . 'adapter-sign-key'),
                $orderSign
            );

            $this->assertTrue(
                SignServiceAdapterProbe::verifyCreateOrderSign(
                    'merchant-001',
                    'attach',
                    1,
                    '12.34',
                    md5('merchant-001' . 'attach' . 1 . '12.34' . 'adapter-sign-key')
                )
            );

            $this->assertTrue(
                SignServiceAdapterProbe::verifySimpleSign(
                    'heartbeat',
                    md5('heartbeat' . 'adapter-sign-key')
                )
            );
        }

        public function test_terminal_monitor_push_signature_uses_terminal_specific_key_and_terminal_code_payload(): void
        {
            $this->assertTrue(
                method_exists(SignServiceAdapterProbe::class, 'verifyTerminalMonitorPushSign'),
                'Multi-terminal callbacks need a terminal-aware monitor signature verifier.'
            );

            SignServiceAdapterProbe::$credentials = new class extends TerminalCredentialService {
                public function requireKeyFor(string $terminalCode): string
                {
                    return $terminalCode === 'term-a' ? 'terminal-sign-key' : 'legacy-sign-key';
                }
            };

            $payload = implode('|', ['term-a', 1, 1234, 1712300000, 'nonce-1', 'evt-1']);
            $sign = hash_hmac('sha256', $payload, 'terminal-sign-key');

            $this->assertTrue(
                SignServiceAdapterProbe::verifyTerminalMonitorPushSign(
                    'term-a',
                    1,
                    1234,
                    1712300000,
                    'nonce-1',
                    'evt-1',
                    $sign
                )
            );

            $this->assertFalse(
                SignServiceAdapterProbe::verifyTerminalMonitorPushSign(
                    'term-a',
                    1,
                    1234,
                    1712300000,
                    'nonce-1',
                    'evt-1',
                    hash_hmac('sha256', $payload, 'monitor-sign-key')
                )
            );
        }

        public function test_terminal_monitor_simple_signature_uses_terminal_specific_key(): void
        {
            $this->assertTrue(
                method_exists(SignServiceAdapterProbe::class, 'verifyTerminalMonitorSimpleSign'),
                'Multi-terminal heartbeat/state checks need a terminal-aware simple signer.'
            );

            SignServiceAdapterProbe::$credentials = new class extends TerminalCredentialService {
                public function requireKeyFor(string $terminalCode): string
                {
                    return $terminalCode === 'term-a' ? 'terminal-monitor-key' : 'legacy-monitor-key';
                }
            };

            $data = '1712300000000';

            $this->assertTrue(
                SignServiceAdapterProbe::verifyTerminalMonitorSimpleSign(
                    'term-a',
                    $data,
                    md5($data . 'terminal-monitor-key')
                )
            );

            $this->assertFalse(
                SignServiceAdapterProbe::verifyTerminalMonitorSimpleSign(
                    'term-a',
                    $data,
                    md5($data . 'monitor-sign-key')
                )
            );
        }

        public function test_monitor_service_can_write_terminal_heartbeat_with_ip(): void
        {
            $this->assertTrue(
                method_exists(MonitorServiceAdapterProbe::class, 'heartbeatForTerminal'),
                'Multi-terminal status tracking needs a terminal heartbeat writer.'
            );

            MonitorServiceAdapterProbe::$now = 1713888000;

            MonitorServiceAdapterProbe::heartbeatForTerminal(7, '127.0.0.1');

            $this->assertSame([
                [
                    'terminal_id' => 7,
                    'last_heartbeat_at' => 1713888000,
                    'last_ip' => '127.0.0.1',
                    'online_state' => 'online',
                    'updated_at' => 1713888000,
                ],
            ], MonitorServiceAdapterProbe::$terminalHeartbeatWrites);
        }

        public function test_notify_service_returns_empty_string_for_unsupported_schemes_before_http_execution(): void
        {
            NotifyHttpProbe::enable();

            $this->assertSame('', NotifyServiceAdapterProbe::httpGet('ftp://merchant.example/notify'));
            $this->assertSame([], NotifyHttpProbe::$curlOptions);
        }

        public function test_notify_service_blocks_private_ip_literals_before_http_execution(): void
        {
            NotifyServiceAdapterProbe::$config = new FakeSystemConfig(notifySslVerifyEnabled: true);
            NotifyHttpProbe::enable();

            $result = NotifyServiceAdapterProbe::sendNotifyDetailed([
                'notify_url' => 'http://127.0.0.1/internal-notify',
                'pay_id' => 'merchant-local-1001',
                'param' => 'attach',
                'type' => PayOrder::TYPE_WECHAT,
                'price' => '12.34',
                'really_price' => '12.34',
            ]);

            $this->assertFalse($result['ok']);
            $this->assertStringContainsString('通知地址指向内网地址', $result['detail']);
            $this->assertSame([], NotifyHttpProbe::$curlOptions);
        }

        public function test_notify_service_blocks_private_ipv6_literals_before_http_execution(): void
        {
            NotifyServiceAdapterProbe::$config = new FakeSystemConfig(notifySslVerifyEnabled: true);
            NotifyHttpProbe::enable();

            $result = NotifyServiceAdapterProbe::sendNotifyDetailed([
                'notify_url' => 'http://[::1]/internal-notify',
                'pay_id' => 'merchant-local-ipv6-1001',
                'param' => 'attach',
                'type' => PayOrder::TYPE_WECHAT,
                'price' => '12.34',
                'really_price' => '12.34',
            ]);

            $this->assertFalse($result['ok']);
            $this->assertStringContainsString('通知地址指向内网地址', $result['detail']);
            $this->assertSame([], NotifyHttpProbe::$curlOptions);
        }

        public function test_notify_service_reads_ssl_verify_flag_through_config_adapter(): void
        {
            $this->seedSettings(['notify_ssl_verify' => '1']);

            NotifyServiceAdapterProbe::$config = new FakeSystemConfig(notifySslVerifyEnabled: false);
            NotifyHttpProbe::enable(
                hostMap: ['merchant.example' => '93.184.216.34'],
                result: 'success'
            );

            $this->assertSame('success', NotifyServiceAdapterProbe::httpGet('https://merchant.example/notify'));
            $this->assertFalse(NotifyHttpProbe::$curlOptions[CURLOPT_SSL_VERIFYPEER] ?? true);
            $this->assertSame(0, NotifyHttpProbe::$curlOptions[CURLOPT_SSL_VERIFYHOST] ?? 2);
        }

        public function test_notify_service_includes_curl_errno_and_http_code_in_failure_detail(): void
        {
            NotifyServiceAdapterProbe::$config = new FakeSystemConfig(notifySslVerifyEnabled: true);
            NotifyHttpProbe::enable(
                hostMap: ['merchant.example' => '93.184.216.34'],
                result: false,
                error: 'Empty reply from server'
            );
            NotifyHttpProbe::$info = [
                CURLINFO_HTTP_CODE => 200,
                CURLINFO_EFFECTIVE_URL => 'https://merchant.example/notify?foo=bar',
                CURLINFO_PRIMARY_IP => '93.184.216.34',
            ];
            $result = NotifyServiceAdapterProbe::sendNotifyDetailed([
                'notify_url' => 'https://merchant.example/notify',
                'pay_id' => 'merchant-1001',
                'param' => 'attach',
                'type' => PayOrder::TYPE_WECHAT,
                'price' => '12.34',
                'really_price' => '12.34',
            ]);

            $this->assertFalse($result['ok']);
            $this->assertStringContainsString('通知请求失败: Empty reply from server', $result['detail']);
            $this->assertStringContainsString('curl_errno=52', $result['detail']);
            $this->assertStringContainsString('http_code=200', $result['detail']);
            $this->assertStringContainsString('primary_ip=93.184.216.34', $result['detail']);
        }

        public function test_order_creation_kernel_preserves_raw_timeout_string_in_payload_and_cache(): void
        {
            $this->seedSettings(['close' => '99']);
            OrderCreationKernelProbe::$config = new FakeSystemConfig(
                orderCloseMinutes: 99,
                orderCloseRaw: '05'
            );

            $payload = OrderCreationKernelProbe::buildAndCacheOrderInfo(
                'merchant-002',
                'order-002',
                PayOrder::TYPE_WECHAT,
                '12.34',
                '12.34',
                'weixin://pay-url',
                1,
                1700000000
            );

            $this->assertSame('05', $payload['timeOut']);
            $this->assertSame($payload, OrderCreationKernelProbe::$cachedOrders['order-002'] ?? null);
        }

        public function test_order_service_handle_terminal_pay_push_first_match_returns_notify_ok_shape(): void
        {
            // Ensure the notify path is deterministic and doesn't touch real network.
            NotifyHttpProbe::enable(
                hostMap: ['merchant.example' => '93.184.216.34'],
                result: 'success'
            );

            // SignService reads the signing key through SettingSystemConfig -> Setting (aliased to FakeSetting).
            $this->seedSettings(['key' => 'test-sign-key']);

            // Seed an unpaid order that should be matched and processed exactly once.
            PayOrder::reset();
            \app\model\TmpPrice::reset();

            PayOrder::seed([
                'close_date' => 0,
                'create_date' => 1700000000,
                'is_auto' => 0,
                'notify_url' => 'https://merchant.example/notify',
                'order_id' => 'order-1001',
                'param' => 'attach',
                'pay_date' => 0,
                'pay_id' => 'merchant-1001',
                'pay_url' => 'weixin://pay-url',
                'price' => '12.34',
                'really_price' => '12.34',
                'return_url' => 'https://merchant.example/return',
                'terminal_id' => 1,
                'channel_id' => 1,
                'state' => PayOrder::STATE_UNPAID,
                'type' => PayOrder::TYPE_WECHAT,
            ]);
            \app\model\TmpPrice::seed('order-1001', '1234-1');

            $result = OrderServiceAdapterProbe::handleTerminalPayPush(1, '12.34', PayOrder::TYPE_WECHAT, '', []);

            $this->assertSame([
                'matched' => true,
                'alreadyProcessed' => false,
                'notifyOk' => true,
                'notifyDetail' => '',
            ], $result);
        }

        public function test_order_service_handle_terminal_pay_push_returns_notify_failure_detail(): void
        {
            NotifyHttpProbe::enable(
                hostMap: ['merchant.example' => '93.184.216.34'],
                result: 'gateway error'
            );
            $this->seedSettings(['key' => 'test-sign-key']);

            PayOrder::reset();
            \app\model\TmpPrice::reset();

            PayOrder::seed([
                'close_date' => 0,
                'create_date' => 1700000000,
                'is_auto' => 0,
                'notify_url' => 'https://merchant.example/notify',
                'order_id' => 'order-notify-fail-1001',
                'param' => 'attach',
                'pay_date' => 0,
                'pay_id' => 'merchant-notify-fail-1001',
                'pay_url' => 'weixin://pay-url',
                'price' => '12.34',
                'really_price' => '12.34',
                'return_url' => 'https://merchant.example/return',
                'terminal_id' => 1,
                'channel_id' => 1,
                'state' => PayOrder::STATE_UNPAID,
                'type' => PayOrder::TYPE_WECHAT,
            ]);
            \app\model\TmpPrice::seed('order-notify-fail-1001', '1234-1');

            $result = OrderServiceAdapterProbe::handleTerminalPayPush(1, '12.34', PayOrder::TYPE_WECHAT, '', []);

            $this->assertSame([
                'matched' => true,
                'alreadyProcessed' => false,
                'notifyOk' => false,
                'notifyDetail' => '通知接口返回: gateway error',
            ], $result);
        }

        public function test_order_service_handle_terminal_pay_push_rolls_back_state_when_tmp_price_cleanup_fails(): void
        {
            NotifyHttpProbe::enable(
                hostMap: ['merchant.example' => '93.184.216.34'],
                result: 'success'
            );
            $this->seedSettings(['key' => 'test-sign-key']);

            PayOrder::reset();
            \app\model\TmpPrice::reset();

            PayOrder::seed([
                'close_date' => 0,
                'create_date' => 1700000000,
                'is_auto' => 0,
                'notify_url' => 'https://merchant.example/notify',
                'order_id' => 'order-rollback-1001',
                'param' => 'attach',
                'pay_date' => 0,
                'pay_id' => 'merchant-rollback-1001',
                'pay_url' => 'weixin://pay-url',
                'price' => '45.67',
                'really_price' => '45.67',
                'return_url' => 'https://merchant.example/return',
                'terminal_id' => 1,
                'channel_id' => 1,
                'state' => PayOrder::STATE_UNPAID,
                'type' => PayOrder::TYPE_WECHAT,
            ]);
            \app\model\TmpPrice::seed('order-rollback-1001', '4567-1');
            \app\model\TmpPrice::$throwOnDelete = true;

            try {
                OrderServiceAdapterProbe::handleTerminalPayPush(1, '45.67', PayOrder::TYPE_WECHAT, '', []);
                $this->fail('Expected tmp_price cleanup failure to bubble up.');
            } catch (\RuntimeException $e) {
                $this->assertSame('tmp price delete failed', $e->getMessage());
            } finally {
                \app\model\TmpPrice::$throwOnDelete = false;
            }

            $order = PayOrder::where('order_id', 'order-rollback-1001')->findOrFail();
            $this->assertSame(PayOrder::STATE_UNPAID, $order['state']);
            $this->assertSame(0, $order['pay_date']);
            $this->assertCount(1, \app\model\TmpPrice::allRows());
        }

        private function seedSettings(array $settings): void
        {
            foreach ($settings as $key => $value) {
                FakeSetting::setConfigValue((string) $key, (string) $value);
            }
        }
    }

    class SignServiceAdapterProbe extends SignService
    {
        public static ?SystemConfig $config = null;
        public static ?TerminalCredentialService $credentials = null;

        protected static function systemConfig(): SystemConfig
        {
            if (self::$config === null) {
                throw new \RuntimeException('Test config was not initialized.');
            }

            return self::$config;
        }

        protected static function terminalCredentialService(): TerminalCredentialService
        {
            if (self::$credentials === null) {
                throw new \RuntimeException('Test terminal credential service was not initialized.');
            }

            return self::$credentials;
        }
    }

    class MonitorServiceAdapterProbe extends MonitorService
    {
        public static ?int $now = null;
        public static array $terminalHeartbeatWrites = [];

        protected static function currentTimestamp(): int
        {
            return self::$now ?? time();
        }

        protected static function persistTerminalHeartbeat(int $terminalId, string $ip, int $timestamp): void
        {
            self::$terminalHeartbeatWrites[] = [
                'terminal_id' => $terminalId,
                'last_heartbeat_at' => $timestamp,
                'last_ip' => $ip,
                'online_state' => 'online',
                'updated_at' => $timestamp,
            ];
        }
    }

    class NotifyServiceAdapterProbe extends NotifyService
    {
        public static ?SystemConfig $config = null;

        protected static function systemConfig(): SystemConfig
        {
            if (self::$config === null) {
                throw new \RuntimeException('Test config was not initialized.');
            }

            return self::$config;
        }
    }

    class OrderCreationKernelProbe extends OrderCreationKernel
    {
        public static ?SystemConfig $config = null;
        public static array $cachedOrders = [];

        protected static function systemConfig(): SystemConfig
        {
            if (self::$config === null) {
                throw new \RuntimeException('Test config was not initialized.');
            }

            return self::$config;
        }

        protected static function orderCache(): OrderCache
        {
            return new class extends OrderCache {
                public function cacheOrder(string $orderId, array $orderData): bool
                {
                    OrderCreationKernelProbe::$cachedOrders[$orderId] = $orderData;
                    return true;
                }
            };
        }

        protected static function payloadFactory(): OrderPayloadFactory
        {
            return new OrderPayloadFactory();
        }
    }

    class OrderServiceAdapterProbe extends OrderService
    {
        protected static function runTransaction(callable $callback): mixed
        {
            $rows = PayOrder::allRows();
            $tmpRows = \app\model\TmpPrice::allRows();

            try {
                return $callback();
            } catch (\Throwable $e) {
                PayOrder::replaceRows($rows);
                \app\model\TmpPrice::replaceRows($tmpRows);
                throw $e;
            }
        }

        protected static function markTerminalPaid(int $terminalId, int $timestamp): void
        {
        }
    }

    final class FakeSystemConfig implements SystemConfig
    {
        public function __construct(
            private readonly string $notifyUrl = '',
            private readonly string $returnUrl = '',
            private readonly string $signKey = '',
            private readonly int $orderCloseMinutes = 15,
            private readonly string $orderCloseRaw = '15',
            private readonly string $payQfMode = '0',
            private readonly bool $notifySslVerifyEnabled = true
        ) {
        }

        public function getNotifyUrl(): string
        {
            return $this->notifyUrl;
        }

        public function getReturnUrl(): string
        {
            return $this->returnUrl;
        }

        public function getSignKey(): string
        {
            return $this->signKey;
        }

        public function getOrderCloseMinutes(): int
        {
            return $this->orderCloseMinutes;
        }

        public function getOrderCloseRaw(): string
        {
            return $this->orderCloseRaw;
        }

        public function getPayQfMode(): string
        {
            return $this->payQfMode;
        }

        public function getNotifySslVerifyEnabled(): bool
        {
            return $this->notifySslVerifyEnabled;
        }
    }

    final class FakeSetting
    {
        private static array $values = [];

        public static function reset(): void
        {
            self::$values = [];
        }

        public static function getConfigValue(string $key, string $default = ''): string
        {
            return self::$values[$key] ?? $default;
        }

        public static function setConfigValue(string $key, string $value): void
        {
            self::$values[$key] = $value;
        }
    }

    final class NotifyHttpProbe
    {
        public static bool $enabled = false;
        public static array $hostMap = [];
        public static array $curlOptions = [];
        public static array $info = [];
        public static string|false $result = '';
        public static string $error = '';
        public static int $errno = 52;

        public static function enable(array $hostMap = [], string|false $result = 'success', string $error = ''): void
        {
            self::$enabled = true;
            self::$hostMap = $hostMap;
            self::$curlOptions = [];
            self::$info = [];
            self::$result = $result;
            self::$error = $error;
        }

        public static function reset(): void
        {
            self::$enabled = false;
            self::$hostMap = [];
            self::$curlOptions = [];
            self::$info = [];
            self::$result = '';
            self::$error = '';
            self::$errno = 52;
        }

        public static function curlInit(): mixed
        {
            if (!self::$enabled) {
                return \curl_init();
            }

            return fopen('php://temp', 'rb');
        }

        public static function curlSetOpt(mixed $handle, int $option, mixed $value): bool
        {
            if (!self::$enabled) {
                return \curl_setopt($handle, $option, $value);
            }

            self::$curlOptions[$option] = $value;
            return true;
        }

        public static function curlExec(mixed $handle): string|false
        {
            if (!self::$enabled) {
                return \curl_exec($handle);
            }

            return self::$result;
        }

        public static function curlClose(mixed $handle): void
        {
            if (!self::$enabled) {
                \curl_close($handle);
                return;
            }

            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        public static function curlError(mixed $handle): string
        {
            if (!self::$enabled) {
                return \curl_error($handle);
            }

            return self::$error;
        }

        public static function curlErrno(mixed $handle): int
        {
            if (!self::$enabled) {
                return \curl_errno($handle);
            }

            return self::$result === false ? self::$errno : 0;
        }

        public static function curlGetInfo(mixed $handle, ?int $option = null): mixed
        {
            if (!self::$enabled) {
                return $option === null ? \curl_getinfo($handle) : \curl_getinfo($handle, $option);
            }

            if ($option === null) {
                return self::$info;
            }

            return self::$info[$option] ?? null;
        }

        public static function gethostbyname(string $host): string
        {
            if (!self::$enabled) {
                return \gethostbyname($host);
            }

            return self::$hostMap[$host] ?? $host;
        }
    }
}
