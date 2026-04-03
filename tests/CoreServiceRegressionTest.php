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

    if (!function_exists(__NAMESPACE__ . '\gethostbyname')) {
        function gethostbyname(string $host): string
        {
            return \tests\NotifyHttpProbe::gethostbyname($host);
        }
    }
}

namespace tests {

    use app\service\MonitorService;
    use app\service\NotifyService;
    use app\service\SignService;
    use app\service\config\SystemConfig;
    use app\service\runtime\MonitorState;
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
            MonitorServiceAdapterProbe::$state = null;
            NotifyServiceAdapterProbe::$config = null;

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

        public function test_monitor_service_writes_runtime_state_through_monitor_state_adapter(): void
        {
            $state = new RecordingMonitorState(lastHeartbeatAt: time() - 300, online: true);
            MonitorServiceAdapterProbe::$state = $state;

            MonitorServiceAdapterProbe::heartbeat();

            $this->assertNotSame(0, $state->lastHeartbeatAt);
            $this->assertTrue($state->online);
            $this->assertSame(['heartbeat', 'online'], $state->events);

            $state->events = [];
            $state->lastHeartbeatAt = time() - 120;

            MonitorServiceAdapterProbe::checkMonitorTimeout();

            $this->assertFalse($state->online);
            $this->assertSame(['offline'], $state->events);
        }

        public function test_notify_service_returns_empty_string_for_unsupported_schemes_before_http_execution(): void
        {
            NotifyHttpProbe::enable();

            $this->assertSame('', NotifyServiceAdapterProbe::httpGet('ftp://merchant.example/notify'));
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

        protected static function systemConfig(): SystemConfig
        {
            if (self::$config === null) {
                throw new \RuntimeException('Test config was not initialized.');
            }

            return self::$config;
        }
    }

    class MonitorServiceAdapterProbe extends MonitorService
    {
        public static ?MonitorState $state = null;

        protected static function monitorState(): MonitorState
        {
            if (self::$state === null) {
                throw new \RuntimeException('Test monitor state was not initialized.');
            }

            return self::$state;
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

    final class RecordingMonitorState implements MonitorState
    {
        public array $events = [];

        public function __construct(
            public int $lastHeartbeatAt = 0,
            public int $lastPaidAt = 0,
            public bool $online = false
        ) {
        }

        public function getLastHeartbeatAt(): int
        {
            return $this->lastHeartbeatAt;
        }

        public function getLastPaidAt(): int
        {
            return $this->lastPaidAt;
        }

        public function markHeartbeatAt(int $timestamp): void
        {
            $this->lastHeartbeatAt = $timestamp;
            $this->events[] = 'heartbeat';
        }

        public function markPaidAt(int $timestamp): void
        {
            $this->lastPaidAt = $timestamp;
            $this->events[] = 'paid';
        }

        public function markOnline(): void
        {
            $this->online = true;
            $this->events[] = 'online';
        }

        public function markOffline(): void
        {
            $this->online = false;
            $this->events[] = 'offline';
        }

        public function isOnline(): bool
        {
            return $this->online;
        }
    }

    final class FakeSystemConfig implements SystemConfig
    {
        public function __construct(
            private readonly string $notifyUrl = '',
            private readonly string $returnUrl = '',
            private readonly string $signKey = '',
            private readonly int $orderCloseMinutes = 15,
            private readonly string $payQfMode = '0',
            private readonly string $weChatPayUrl = '',
            private readonly string $alipayPayUrl = '',
            private readonly bool $notifySslVerifyEnabled = true,
            private readonly array $epayConfig = [
                'enabled' => false,
                'pid' => '',
                'key' => '',
                'name' => '订单支付',
                'private_key' => '',
                'public_key' => '',
            ]
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

        public function getPayQfMode(): string
        {
            return $this->payQfMode;
        }

        public function getWeChatPayUrl(): string
        {
            return $this->weChatPayUrl;
        }

        public function getAlipayPayUrl(): string
        {
            return $this->alipayPayUrl;
        }

        public function getNotifySslVerifyEnabled(): bool
        {
            return $this->notifySslVerifyEnabled;
        }

        public function getEpayConfig(): array
        {
            return $this->epayConfig;
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
        public static string|false $result = '';

        public static function enable(array $hostMap = [], string|false $result = 'success'): void
        {
            self::$enabled = true;
            self::$hostMap = $hostMap;
            self::$curlOptions = [];
            self::$result = $result;
        }

        public static function reset(): void
        {
            self::$enabled = false;
            self::$hostMap = [];
            self::$curlOptions = [];
            self::$result = '';
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

        public static function gethostbyname(string $host): string
        {
            if (!self::$enabled) {
                return \gethostbyname($host);
            }

            return self::$hostMap[$host] ?? $host;
        }
    }
}
