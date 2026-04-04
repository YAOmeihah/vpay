<?php
declare(strict_types=1);

namespace tests;

use app\service\CacheService;
use app\service\admin\AdminSettingsService;
use app\service\admin\DashboardStatsService;
use app\service\runtime\SettingMonitorState;
use app\service\security\LoginAttemptLimiter;
use app\command\CacheManage;
use PHPUnit\Framework\TestCase;
use think\App;

class ControllerEdgeServiceRegressionTest extends TestCase
{
    private static App $app;
    private static string $rootPath;
    private static string $cachePath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        self::$app = new App(self::$rootPath);
        self::$app->initialize();
        self::configureCache();
    }

    protected function tearDown(): void
    {
        CacheService::clearAll();
        parent::tearDown();
    }

    public function test_admin_settings_service_keeps_existing_field_list_and_masks_sensitive_values(): void
    {
        if (!class_exists(AdminSettingsService::class)) {
            $this->fail('AdminSettingsService class is missing.');
        }

        $service = new class([
            'user' => 'admin',
            'notifyUrl' => 'https://merchant.example/notify',
            'returnUrl' => 'https://merchant.example/return',
            'key' => '',
            'lastheart' => '1712200000',
            'lastpay' => '1712200300',
            'jkstate' => '1',
            'close' => '15',
            'payQf' => '0',
            'wxpay' => 'weixin://default-pay-url',
            'zfbpay' => 'alipays://default-pay-url',
            'epay_enabled' => '1',
            'epay_pid' => '10001',
            'epay_name' => '订单支付',
            'epay_public_key' => 'PUBLIC-KEY',
        ]) extends AdminSettingsService {
            public array $savedSettings = [];

            public function __construct(private array $settings)
            {
            }

            protected function getConfigValue(string $key, string $default = ''): string
            {
                if (array_key_exists($key, $this->settings)) {
                    return (string) $this->settings[$key];
                }

                return $default;
            }

            protected function setConfigValue(string $key, string $value): bool
            {
                $this->savedSettings[$key] = $value;
                $this->settings[$key] = $value;

                return true;
            }

            protected function generateKey(): string
            {
                return 'generated-sign-key';
            }
        };

        $settings = $service->getSettings();

        $this->assertSame([
            'user',
            'pass',
            'notifyUrl',
            'returnUrl',
            'key',
            'lastheart',
            'lastpay',
            'jkstate',
            'close',
            'payQf',
            'wxpay',
            'zfbpay',
            'epay_enabled',
            'epay_pid',
            'epay_key',
            'epay_name',
            'epay_private_key',
            'epay_public_key',
        ], array_keys($settings));
        $this->assertSame('', $settings['pass']);
        $this->assertSame('', $settings['epay_key']);
        $this->assertSame('', $settings['epay_private_key']);
        $this->assertSame('generated-sign-key', $settings['key']);
        $this->assertSame(['key' => 'generated-sign-key'], $service->savedSettings);
    }

    public function test_admin_settings_service_regenerates_zero_key(): void
    {
        $service = new class([
            'key' => '0',
        ]) extends AdminSettingsService {
            public array $savedSettings = [];

            public function __construct(private array $settings)
            {
            }

            protected function getConfigValue(string $key, string $default = ''): string
            {
                return array_key_exists($key, $this->settings) ? (string) $this->settings[$key] : $default;
            }

            protected function setConfigValue(string $key, string $value): bool
            {
                $this->savedSettings[$key] = $value;
                $this->settings[$key] = $value;

                return true;
            }

            protected function generateKey(): string
            {
                return 'generated-sign-key';
            }
        };

        $settings = $service->getSettings();
        $this->assertSame('generated-sign-key', $settings['key']);
        $this->assertSame(['key' => 'generated-sign-key'], $service->savedSettings);
    }

    public function test_admin_settings_service_ignores_zero_password(): void
    {
        $service = new class([]) extends AdminSettingsService {
            public array $savedSettings = [];

            protected function getConfigValue(string $key, string $default = ''): string
            {
                return $default;
            }

            protected function setConfigValue(string $key, string $value): bool
            {
                $this->savedSettings[$key] = $value;
                return true;
            }
        };

        $service->saveSettings([
            'user' => 'admin',
            'pass' => '0',
        ]);

        $this->assertArrayNotHasKey('pass', $service->savedSettings);
    }

    public function test_admin_settings_service_regenerates_key_when_legacy_empty_semantics_consider_it_empty(): void
    {
        $service = new class(['key' => '0']) extends AdminSettingsService {
            public array $savedSettings = [];

            public function __construct(private array $settings)
            {
            }

            protected function getConfigValue(string $key, string $default = ''): string
            {
                return array_key_exists($key, $this->settings) ? (string) $this->settings[$key] : $default;
            }

            protected function setConfigValue(string $key, string $value): bool
            {
                $this->savedSettings[$key] = $value;
                $this->settings[$key] = $value;
                return true;
            }

            protected function generateKey(): string
            {
                return 'legacy-empty-regenerated-key';
            }
        };

        $settings = $service->getSettings();

        $this->assertSame('legacy-empty-regenerated-key', $settings['key']);
        $this->assertSame(['key' => 'legacy-empty-regenerated-key'], $service->savedSettings);
    }

    public function test_admin_settings_service_ignores_password_value_zero_to_match_legacy_empty_semantics(): void
    {
        $service = new class extends AdminSettingsService {
            public array $savedSettings = [];

            protected function setConfigValue(string $key, string $value): bool
            {
                $this->savedSettings[$key] = $value;
                return true;
            }

            protected function dashboardStatsService(): DashboardStatsService
            {
                return new class extends DashboardStatsService {
                    public function clearStats(): bool
                    {
                        return true;
                    }
                };
            }
        };

        $service->saveSettings([
            'user' => 'next-admin',
            'pass' => '0',
        ]);

        $this->assertSame('next-admin', $service->savedSettings['user'] ?? null);
        $this->assertArrayNotHasKey('pass', $service->savedSettings);
    }

    public function test_dashboard_stats_service_uses_dashboard_cache_slot_semantics(): void
    {
        if (!class_exists(DashboardStatsService::class)) {
            $this->fail('DashboardStatsService class is missing.');
        }

        $service = new DashboardStatsService();
        $buildCalls = 0;
        $expected = [
            'todayOrder' => 8,
            'todaySuccessOrder' => 6,
            'todayCloseOrder' => 1,
            'todayMoney' => 88.66,
        ];

        $first = $service->getStats(function () use (&$buildCalls, $expected): array {
            $buildCalls++;
            return $expected;
        });

        $second = $service->getStats(function (): array {
            throw new \RuntimeException('Cached dashboard stats should be returned.');
        });

        $this->assertSame($expected, $first);
        $this->assertSame($expected, $second);
        $this->assertSame(1, $buildCalls);
        $this->assertSame($expected, CacheService::getStats('dashboard'));
        $this->assertNull(CacheService::getStats('not-dashboard'));

        $this->assertTrue($service->clearStats());
        $this->assertNull(CacheService::getStats('dashboard'));
    }

    public function test_login_attempt_limiter_keeps_threshold_contract_for_login_path(): void
    {
        if (!class_exists(LoginAttemptLimiter::class)) {
            $this->fail('LoginAttemptLimiter class is missing.');
        }

        $limiter = new LoginAttemptLimiter();
        $clientIp = '127.0.0.1';

        $this->assertFalse($limiter->tooManyLoginAttempts($clientIp));

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $limiter->recordLoginFailure($clientIp);
            $this->assertFalse($limiter->tooManyLoginAttempts($clientIp), 'Threshold changed before the fifth failed login attempt.');
        }

        $limiter->recordLoginFailure($clientIp);
        $this->assertTrue($limiter->tooManyLoginAttempts($clientIp));

        $limiter->clearLoginAttempts($clientIp);
        $this->assertFalse($limiter->tooManyLoginAttempts($clientIp));
    }

    public function test_cache_manage_warmup_payload_builder_preserves_legacy_raw_value_shapes(): void
    {
        $command = new class extends CacheManage {
            public function buildPayloadProbe(array $order, string $timeOut): array
            {
                $method = new \ReflectionMethod(CacheManage::class, 'buildWarmOrderPayload');
                $method->setAccessible(true);

                /** @var array<string, mixed> $payload */
                $payload = $method->invoke($this, $order, $timeOut);
                return $payload;
            }
        };

        $payload = $command->buildPayloadProbe([
            'pay_id' => 'merchant-raw',
            'order_id' => 'order-raw',
            'type' => '2',
            'price' => '12.34',
            'really_price' => '12.35',
            'pay_url' => 'alipays://raw-pay-url',
            'is_auto' => '0',
            'state' => '-1',
            'create_date' => '1700000000',
        ], '05');

        $this->assertSame([
            'payId' => 'merchant-raw',
            'orderId' => 'order-raw',
            'payType' => '2',
            'price' => '12.34',
            'reallyPrice' => '12.35',
            'payUrl' => 'alipays://raw-pay-url',
            'isAuto' => '0',
            'state' => '-1',
            'timeOut' => '05',
            'date' => '1700000000',
        ], $payload);
    }

    public function test_monitor_state_raw_accessors_preserve_empty_string_values(): void
    {
        $state = new class extends SettingMonitorState {
            /**
             * @var array<string, string>
             */
            private array $values = [
                'lastheart' => '',
                'lastpay' => '',
                'jkstate' => '',
            ];

            protected function getConfigValue(string $key, string $default = ''): string
            {
                return $this->values[$key] ?? $default;
            }

            protected function setConfigValue(string $key, string $value): bool
            {
                $this->values[$key] = $value;

                return true;
            }
        };

        $this->assertSame('', $state->getLastHeartbeatRaw());
        $this->assertSame('', $state->getLastPaidRaw());
        $this->assertSame('', $state->getOnlineFlagRaw());
        $this->assertSame(0, $state->getLastHeartbeatAt());
        $this->assertSame(0, $state->getLastPaidAt());
        $this->assertFalse($state->isOnline());
    }

    private static function configureCache(): void
    {
        self::$cachePath = self::$rootPath . 'runtime' . DIRECTORY_SEPARATOR . 'phpunit-cache-edge' . DIRECTORY_SEPARATOR;
        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0777, true);
        }

        $cacheConfig = self::$app->config->get('cache');
        $cacheConfig['default'] = 'file';
        $cacheConfig['stores']['file']['path'] = self::$cachePath;

        self::$app->config->set($cacheConfig, 'cache');
        CacheService::clearAll();
    }
}
