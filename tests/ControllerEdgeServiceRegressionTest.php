<?php
declare(strict_types=1);

namespace tests;

use app\controller\Admin;
use app\controller\merchant\Order as MerchantOrderController;
use app\service\CacheService;
use app\service\admin\AdminPermissionService;
use app\service\admin\AdminSettingsService;
use app\service\admin\DashboardStatsService;
use app\service\order\ExpiredOrderCleanupGate;
use app\service\order\OrderStateManager;
use app\service\security\LoginAttemptLimiter;
use app\command\CacheManage;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use PHPUnit\Framework\TestCase;
use think\App;
use think\facade\View;

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

    public function test_merchant_order_html_error_page_uses_payment_style_shell(): void
    {
        self::$app->request->setLayer('merchant');
        self::$app->request->setController('Order');
        self::$app->view->forgetDriver();

        $controller = new MerchantOrderController(self::$app);
        $method = new \ReflectionMethod($controller, 'renderErrorHtml');
        $method->setAccessible(true);

        $html = $method->invoke($controller, '监控端状态异常，请检查');

        $this->assertStringContainsString('payment-error-shell', $html);
        $this->assertStringContainsString('payment-error-card', $html);
        $this->assertStringContainsString('安全收银台', $html);
        $this->assertStringContainsString('监控端状态异常', $html);
        $this->assertStringContainsString('payment-error-icon', $html);
        $this->assertStringContainsString('payment-error-header', $html);
        $this->assertStringContainsString('history.back()', $html);

        self::$app->request->setLayer('');
        self::$app->request->setController('');
    }

    public function test_default_view_configuration_can_render_merchant_error_template(): void
    {
        self::$app->view->forgetDriver();

        $html = View::fetch('merchant/error', [
            'title' => '监控端状态异常',
            'message' => '监控端状态异常，请检查',
            'helpText' => '请确认监控端恢复在线后，再重新发起支付。',
            'buttonText' => '返回上页',
        ]);

        $this->assertStringContainsString('<title>监控端状态异常</title>', $html);
        $this->assertStringContainsString('payment-error-shell', $html);
        $this->assertStringContainsString('返回上页', $html);
    }

    public function test_merchant_order_html_error_page_maps_duplicate_order_message(): void
    {
        self::$app->request->setLayer('merchant');
        self::$app->request->setController('Order');
        self::$app->view->forgetDriver();

        $controller = new MerchantOrderController(self::$app);
        $method = new \ReflectionMethod($controller, 'renderErrorHtml');
        $method->setAccessible(true);

        $html = $method->invoke($controller, '商户订单号已存在');

        $this->assertStringContainsString('<title>商户订单重复</title>', $html);
        $this->assertStringContainsString('商户订单号已存在', $html);
        $this->assertStringContainsString('请更换商户订单号后，再重新发起支付。', $html);

        self::$app->request->setLayer('');
        self::$app->request->setController('');
    }

    public function test_merchant_order_html_error_page_maps_capacity_message(): void
    {
        self::$app->request->setLayer('merchant');
        self::$app->request->setController('Order');
        self::$app->view->forgetDriver();

        $controller = new MerchantOrderController(self::$app);
        $method = new \ReflectionMethod($controller, 'renderErrorHtml');
        $method->setAccessible(true);

        $html = $method->invoke($controller, '订单超出负荷，请稍后重试');

        $this->assertStringContainsString('<title>当前下单繁忙</title>', $html);
        $this->assertStringContainsString('订单超出负荷，请稍后重试', $html);
        $this->assertStringContainsString('系统正在处理较多订单，请稍后重试。', $html);

        self::$app->request->setLayer('');
        self::$app->request->setController('');
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
            'notify_ssl_verify' => '0',
            'close' => '15',
            'payQf' => '0',
        ]) extends AdminSettingsService {
            public array $savedSettings = [];
            public array $settings;

            public function __construct(array $settings)
            {
                $this->settings = $settings;
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
            'notify_ssl_verify',
            'close',
            'payQf',
            'allocationStrategy',
        ], array_keys($settings));
        $this->assertSame('admin', $settings['user']);
        $this->assertSame('', $settings['pass']);
        $this->assertSame('generated-sign-key', $settings['key']);
        $this->assertSame([
            'key' => 'generated-sign-key',
        ], $service->savedSettings);
    }

    public function test_admin_settings_service_regenerates_zero_key(): void
    {
        $service = new class([
            'key' => '0',
        ]) extends AdminSettingsService {
            public array $savedSettings = [];
            public array $settings;

            public function __construct(array $settings)
            {
                $this->settings = $settings;
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
        $this->assertSame([
            'key' => 'generated-sign-key',
        ], $service->savedSettings);
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
            public array $settings;

            public function __construct(array $settings)
            {
                $this->settings = $settings;
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
        $this->assertSame([
            'key' => 'legacy-empty-regenerated-key',
        ], $service->savedSettings);
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

    public function test_admin_settings_service_accepts_partial_payment_payload_without_touching_other_sections(): void
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
            'notifyUrl' => 'https://merchant.example/new-notify',
            'returnUrl' => 'https://merchant.example/new-return',
            'key' => 'next-sign-key',
            'notify_ssl_verify' => '0',
            'close' => '30',
            'payQf' => '2',
        ]);

        $this->assertSame([
            'notifyUrl' => 'https://merchant.example/new-notify',
            'returnUrl' => 'https://merchant.example/new-return',
            'key' => 'next-sign-key',
            'notify_ssl_verify' => '0',
            'close' => '30',
            'payQf' => '2',
        ], $service->savedSettings);
    }

    public function test_admin_permission_service_keeps_canonical_admin_permissions_list(): void
    {
        if (!class_exists(AdminPermissionService::class)) {
            $this->fail('AdminPermissionService class is missing.');
        }

        $service = new AdminPermissionService();

        $this->assertSame([
            'dashboard:view',
            'settings:view',
            'settings:save',
            'monitor:view',
            'terminals:view',
            'terminals:save',
            'terminals:toggle',
            'channels:view',
            'channels:save',
            'channels:toggle',
            'qrcode:add',
            'qrcode:view',
            'qrcode:delete',
            'orders:view',
            'orders:delete',
            'orders:repair',
            'orders:cleanup',
        ], $service->all());
    }

    public function test_dashboard_stats_service_uses_dashboard_cache_slot_semantics(): void
    {
        if (!class_exists(DashboardStatsService::class)) {
            $this->fail('DashboardStatsService class is missing.');
        }

        $cache = new class extends \app\service\cache\DashboardStatsCache {
            public array $store = [];

            public function cacheStats(array $stats): bool
            {
                $this->store['dashboard'] = $stats;
                return true;
            }

            public function getStats()
            {
                return $this->store['dashboard'] ?? null;
            }

            public function deleteStats(): bool
            {
                unset($this->store['dashboard']);
                return true;
            }
        };

        $service = new class($cache) extends DashboardStatsService {
            public function __construct(private \app\service\cache\DashboardStatsCache $cache)
            {
            }

            protected function dashboardCache(): \app\service\cache\DashboardStatsCache
            {
                return $this->cache;
            }
        };

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
        $this->assertSame($expected, $cache->getStats());
        $this->assertNull($cache->store['not-dashboard'] ?? null);

        $service->clearStats();
        $this->assertNull($cache->getStats());
    }

    public function test_login_attempt_limiter_keeps_threshold_contract_for_login_path(): void
    {
        if (!class_exists(LoginAttemptLimiter::class)) {
            $this->fail('LoginAttemptLimiter class is missing.');
        }

        $limiter = new LoginAttemptLimiter();
        $clientIp = '127.0.0.1-' . uniqid('', true);

        $this->assertFalse($limiter->tooManyLoginAttempts($clientIp));

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $limiter->recordLoginFailure($clientIp);
            $this->assertFalse($limiter->tooManyLoginAttempts($clientIp), 'Threshold changed before the fifth failed login attempt.');
        }

        $limiter->recordLoginFailure($clientIp);
        $this->assertTrue($limiter->tooManyLoginAttempts($clientIp));
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

    public function test_monitor_controller_uses_terminal_signature_verifier_for_heartbeat_and_state(): void
    {
        $source = (string) file_get_contents(self::$rootPath . 'app/controller/monitor/Monitor.php');

        $this->assertStringContainsString(
            'verifyTerminalMonitorSimpleSignature',
            $source,
            'Monitor controller should route heartbeat/state checks through terminal-specific signing.'
        );
        $this->assertStringNotContainsString('verifyMonitorSimpleSignature', $source);
        $this->assertStringNotContainsString(
            'verifySimpleSign($t, $this->request->param(\'sign\', \'\'))',
            $source,
            'Heartbeat/state endpoints should no longer verify against the merchant key.'
        );
    }

    public function test_index_controller_no_longer_exposes_legacy_monitor_compat_entrypoints(): void
    {
        $source = (string) file_get_contents(self::$rootPath . 'app/controller/Index.php');

        $this->assertStringNotContainsString('public function getState()', $source);
        $this->assertStringNotContainsString('public function appHeart()', $source);
        $this->assertStringNotContainsString('public function appPush()', $source);
        $this->assertStringNotContainsString('public function closeEndOrder()', $source);
        $this->assertStringNotContainsString("\\app\\controller\\monitor\\Monitor::class", $source);
    }

    public function test_order_state_manager_invalidates_order_and_dashboard_cache_together(): void
    {
        if (!class_exists(OrderStateManager::class)) {
            $this->fail('OrderStateManager class is missing.');
        }

        CacheService::cacheOrder('order-cache-001', [
            'orderId' => 'order-cache-001',
            'state' => 0,
        ]);
        CacheService::cacheStats('dashboard', [
            'todayOrder' => 1,
        ]);

        $manager = new OrderStateManager();
        $manager->invalidateOrderView('order-cache-001');

        $this->assertNull(CacheService::getOrder('order-cache-001'));
        $this->assertNull(CacheService::getStats('dashboard'));
    }

    public function test_expired_order_cleanup_gate_throttles_hot_path_runs_but_allows_forced_execution(): void
    {
        if (!class_exists(ExpiredOrderCleanupGate::class)) {
            $this->fail('ExpiredOrderCleanupGate class is missing.');
        }

        $gate = new ExpiredOrderCleanupGate(30);

        $this->assertTrue($gate->shouldRun());
        $this->assertFalse($gate->shouldRun());
        $this->assertTrue($gate->shouldRun(true));
    }

    public function test_schema_dump_uses_decimal_money_columns_and_unique_order_constraints(): void
    {
        $schema = strtolower((string) file_get_contents(self::$rootPath . 'vmq.sql'));

        $this->assertStringContainsString('`notify_url` varchar(1000)', $schema);
        $this->assertStringContainsString('`pay_url` varchar(1000)', $schema);
        $this->assertStringContainsString('`return_url` varchar(1000)', $schema);
        $this->assertStringContainsString('`vvalue` text', $schema);
        $this->assertStringContainsString('`price` decimal(10,2) not null', $schema);
        $this->assertStringContainsString('`really_price` decimal(10,2) not null', $schema);
        $this->assertStringContainsString('install_status', $schema);
        $this->assertStringContainsString('schema_version', $schema);
        $this->assertStringContainsString('app_version', $schema);
        $this->assertStringContainsString('add unique key `uniq_pay_id` (`pay_id`)', $schema);
        $this->assertStringContainsString('add unique key `uniq_order_id` (`order_id`)', $schema);
        $this->assertStringContainsString('add unique key `uniq_type_price` (`type`,`price`)', $schema);
        $this->assertStringContainsString('add index `idx_really_price_state_type` (`really_price`,`state`,`type`)', $schema);
    }

    public function test_admin_sys_uptime_degrades_gracefully_when_proc_probe_is_blocked(): void
    {
        $controller = new class(self::$app) extends Admin {
            public bool $osProbeUsed = false;
            public bool $procProbeUsed = false;

            protected function currentOsFamily(): string
            {
                $this->osProbeUsed = true;
                return 'Linux';
            }

            protected function readLinuxUptimeRaw(): string|false
            {
                $this->procProbeUsed = true;
                return false;
            }
        };

        $method = new \ReflectionMethod(Admin::class, 'sys_uptime');
        $method->setAccessible(true);
        $result = $method->invoke($controller);

        $this->assertTrue($controller->osProbeUsed);
        $this->assertTrue($controller->procProbeUsed);
        $this->assertSame('无法获取', $result);
    }

    public function test_admin_decode_qrcode_uses_business_error_code_for_decode_failures(): void
    {
        $source = (string) file_get_contents(self::$rootPath . 'app/controller/Admin.php');

        $this->assertStringContainsString(
            'getReturn(-2, "二维码识别失败")',
            $source,
            'QR decode failure should not reuse the -1 unauthorized code path.'
        );
        $this->assertStringContainsString(
            'readFromBlob($imageBlob)',
            $source,
            'Admin QR decode should use the maintained Composer decoder blob API.'
        );
        $this->assertStringNotContainsString(
            'QrReader',
            $source,
            'Admin QR decode should no longer depend on the legacy bundled QrReader.'
        );
    }

    public function test_chillerlan_decoder_can_read_a_generated_payment_qr_blob(): void
    {
        $payload = 'weixin://wxpay/mock-merchant-pay-code';
        $qrCode = new \Endroid\QrCode\QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Low,
            size: 240,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );
        $blob = (new PngWriter())->write($qrCode)->getString();

        $result = (new QRCode(new QROptions([
            'readerUseImagickIfAvailable' => true,
        ])))->readFromBlob($blob);

        $this->assertSame($payload, trim((string) $result));
    }

    public function test_admin_auth_session_flow_uses_framework_session_api_only(): void
    {
        $authController = (string) file_get_contents(self::$rootPath . 'app/controller/admin/Auth.php');
        $adminController = (string) file_get_contents(self::$rootPath . 'app/controller/Admin.php');

        $this->assertStringContainsString('Session::regenerate(false);', $authController);
        $this->assertStringNotContainsString('session_start(', $authController);
        $this->assertStringNotContainsString('session_regenerate_id(', $authController);

        $this->assertStringNotContainsString('session_start(', $adminController);
        $this->assertStringNotContainsString('session_regenerate_id(', $adminController);
    }

    private static function configureCache(): void
    {
        $suffix = substr(sha1(self::$rootPath), 0, 12);
        self::$cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-phpunit-cache-edge-' . $suffix . DIRECTORY_SEPARATOR;
        if (!is_dir(self::$cachePath) && !@mkdir(self::$cachePath, 0777, true) && !is_dir(self::$cachePath)) {
            throw new \RuntimeException('Failed to create PHPUnit cache directory: ' . self::$cachePath);
        }

        $cacheConfig = self::$app->config->get('cache');
        $cacheConfig['default'] = 'file';
        $cacheConfig['stores']['file']['path'] = self::$cachePath;

        self::$app->config->set($cacheConfig, 'cache');
        CacheService::clearAll();
    }
}
