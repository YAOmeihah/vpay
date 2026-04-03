<?php
declare(strict_types=1);

namespace tests;

use app\service\CacheService;
use app\service\admin\AdminSettingsService;
use app\service\admin\DashboardStatsService;
use app\service\security\LoginAttemptLimiter;
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
