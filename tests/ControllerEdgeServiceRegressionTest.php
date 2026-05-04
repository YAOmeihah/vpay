<?php
declare(strict_types=1);

namespace tests;

use app\controller\Admin;
use app\controller\admin\Auth as AdminAuthController;
use app\controller\merchant\Order as MerchantOrderController;
use app\service\CacheService;
use app\service\OrderCreationKernel;
use app\service\admin\AdminPermissionService;
use app\service\admin\AdminSettingsService;
use app\service\admin\DashboardStatsService;
use app\service\order\ExpiredOrderCleanupGate;
use app\service\order\OrderStateManager;
use app\service\security\KeyEncryptionService;
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

    public function test_merchant_payment_choice_route_and_controller_action_are_registered(): void
    {
        $routeSource = (string) file_get_contents(self::$rootPath . 'route/merchant.php');
        $controllerSource = (string) file_get_contents(self::$rootPath . 'app/controller/merchant/Order.php');

        $this->assertStringContainsString("Route::any('selectOrderPayType', 'merchant.Order/selectOrderPayType');", $routeSource);
        $this->assertStringContainsString('public function selectOrderPayType()', $controllerSource);
        $this->assertStringContainsString('OrderService::selectOrderPayType', $controllerSource);
        $this->assertStringContainsString('OrderService::buildPayloadFromOrder', $controllerSource);
    }

    public function test_merchant_check_order_keeps_only_unpaid_and_expired_as_error_states(): void
    {
        $controllerSource = (string) file_get_contents(self::$rootPath . 'app/controller/merchant/Order.php');

        $this->assertStringContainsString('PayOrder::STATE_UNPAID', $controllerSource);
        $this->assertStringContainsString('PayOrder::STATE_EXPIRED', $controllerSource);
        $this->assertStringContainsString('订单已过期', $controllerSource);
        $this->assertStringNotContainsString('PayOrder::STATE_CANCELLED', $controllerSource);
        $this->assertStringNotContainsString('PayOrder::STATE_ASSIGN_FAILED', $controllerSource);
    }

    public function test_admin_write_routes_use_post_without_touching_merchant_contracts(): void
    {
        $adminRoutes = (string) file_get_contents(self::$rootPath . 'route/admin.php');
        $merchantRoutes = (string) file_get_contents(self::$rootPath . 'route/merchant.php');

        $this->assertStringContainsString("Route::post('login', 'admin.Auth/login');", $adminRoutes);
        $this->assertStringContainsString("Route::post('saveSetting', 'admin/saveSetting');", $adminRoutes);
        $this->assertStringContainsString("Route::post('addPayQrcode', 'admin/addPayQrcode');", $adminRoutes);
        $this->assertStringContainsString("Route::post('delPayQrcode', 'admin/delPayQrcode');", $adminRoutes);
        $this->assertStringContainsString("Route::post('delOrder', 'admin/delOrder');", $adminRoutes);
        $this->assertStringContainsString("Route::post('setBd', 'admin/setBd');", $adminRoutes);
        $this->assertStringContainsString("Route::post('delGqOrder', 'admin/delGqOrder');", $adminRoutes);
        $this->assertStringContainsString("Route::post('delLastOrder', 'admin/delLastOrder');", $adminRoutes);

        $this->assertStringContainsString("Route::any('createOrder', 'merchant.Order/createOrder');", $merchantRoutes);
        $this->assertStringContainsString("Route::any('getOrder', 'merchant.Order/getOrder');", $merchantRoutes);
        $this->assertStringContainsString("Route::any('checkOrder', 'merchant.Order/checkOrder');", $merchantRoutes);
        $this->assertStringContainsString("Route::any('closeOrder', 'merchant.Order/closeOrder');", $merchantRoutes);
    }

    public function test_cookie_configuration_hardens_session_cookie_defaults(): void
    {
        $cookieConfig = (string) file_get_contents(self::$rootPath . 'config/cookie.php');
        $securityConfig = (string) file_get_contents(self::$rootPath . 'config/security.php');
        $sessionConfig = (string) file_get_contents(self::$rootPath . 'config/session.php');

        $this->assertStringContainsString("'secure'    => env('COOKIE_SECURE', !filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)),", $cookieConfig);
        $this->assertStringContainsString("'httponly'  => true,", $cookieConfig);
        $this->assertStringContainsString("'samesite'  => 'lax',", $cookieConfig);
        $this->assertStringContainsString("'Strict-Transport-Security'", $securityConfig);
        $this->assertStringContainsString("env('SESSION_TYPE', 'cache')", $sessionConfig);
    }

    public function test_security_config_does_not_expose_login_ip_binding_toggle(): void
    {
        $securityConfig = (string) file_get_contents(self::$rootPath . 'config/security.php');

        $this->assertStringNotContainsString("'check_ip'", $securityConfig);
    }

    public function test_github_release_client_checks_http_status_in_stream_fallback(): void
    {
        $source = (string) file_get_contents(self::$rootPath . 'app/service/update/GitHubReleaseClient.php');

        $this->assertStringContainsString('$http_response_header', $source);
        $this->assertStringContainsString('HTTP status', $source);
        $this->assertStringContainsString('array_reverse', $source);
        $this->assertStringNotContainsString('$http_response_header[0]', $source);
    }

    public function test_platform_order_ids_are_opaque_high_entropy_values(): void
    {
        $first = OrderCreationKernel::generatePlatformOrderId();
        $second = OrderCreationKernel::generatePlatformOrderId();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $first);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_security_sensitive_generators_do_not_fall_back_to_predictable_values(): void
    {
        foreach ([
            'app/service/admin/AdminSettingsService.php',
            'app/service/admin/TerminalAdminService.php',
            'app/service/install/AdminBootstrapService.php',
        ] as $relativePath) {
            $source = (string) file_get_contents(self::$rootPath . $relativePath);

            $this->assertStringContainsString('random_bytes(16)', $source, $relativePath);
            $this->assertStringNotContainsString('mt_rand', $source, $relativePath);
            $this->assertStringNotContainsString('uniqid', $source, $relativePath);
            $this->assertStringNotContainsString('md5(', $source, $relativePath);
        }
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
        $this->assertArrayHasKey('key', $service->savedSettings);
        $this->assertEncryptedSettingValue('generated-sign-key', $service->savedSettings['key']);
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
        $this->assertArrayHasKey('key', $service->savedSettings);
        $this->assertEncryptedSettingValue('generated-sign-key', $service->savedSettings['key']);
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
        $this->assertArrayHasKey('key', $service->savedSettings);
        $this->assertEncryptedSettingValue('legacy-empty-regenerated-key', $service->savedSettings['key']);
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

        $this->assertArrayHasKey('key', $service->savedSettings);
        $this->assertEncryptedSettingValue('next-sign-key', $service->savedSettings['key']);
        unset($service->savedSettings['key']);

        $this->assertSame([
            'notifyUrl' => 'https://merchant.example/new-notify',
            'returnUrl' => 'https://merchant.example/new-return',
            'notify_ssl_verify' => '0',
            'close' => '30',
            'payQf' => '2',
        ], $service->savedSettings);
    }

    private function assertEncryptedSettingValue(string $expectedPlaintext, string $storedValue): void
    {
        $this->assertStringStartsWith('enc:', $storedValue);
        $this->assertSame($expectedPlaintext, (new KeyEncryptionService())->decrypt($storedValue));
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

    public function test_login_attempt_limiter_uses_configured_lockout_time(): void
    {
        $originalSecurity = self::$app->config->get('security');
        $security = is_array($originalSecurity) ? $originalSecurity : [];
        $security['login'] = array_merge($security['login'] ?? [], [
            'lockout_time' => 1800,
        ]);
        self::$app->config->set($security, 'security');

        try {
            $limiter = new class extends LoginAttemptLimiter {
                public ?int $lastTtl = null;

                protected function get(string $key): mixed
                {
                    return 0;
                }

                protected function put(string $key, int $value, int $ttl): void
                {
                    $this->lastTtl = $ttl;
                }
            };

            $limiter->recordLoginFailure('127.0.0.1');

            $this->assertSame(1800, $limiter->lastTtl);
        } finally {
            self::$app->config->set($originalSecurity, 'security');
        }
    }

    public function test_login_attempt_limiter_uses_configured_general_rate_limit(): void
    {
        $originalSecurity = self::$app->config->get('security');
        $security = is_array($originalSecurity) ? $originalSecurity : [];
        $security['rate_limit'] = [
            'max_requests' => 3,
            'window_seconds' => 7,
        ];
        self::$app->config->set($security, 'security');

        try {
            $limiter = new class extends LoginAttemptLimiter {
                public ?int $lastTtl = null;
                /** @var array<string, int> */
                public array $store = [];

                protected function get(string $key): mixed
                {
                    return $this->store[$key] ?? 0;
                }

                protected function put(string $key, int $value, int $ttl): void
                {
                    $this->store[$key] = $value;
                    $this->lastTtl = $ttl;
                }
            };

            $clientIp = '127.0.0.1-rate-limit';
            $this->assertFalse($limiter->tooManyRequests($clientIp));
            $limiter->recordRequest($clientIp);
            $limiter->recordRequest($clientIp);
            $this->assertFalse($limiter->tooManyRequests($clientIp));
            $limiter->recordRequest($clientIp);

            $this->assertTrue($limiter->tooManyRequests($clientIp));
            $this->assertSame(7, $limiter->lastTtl);
        } finally {
            self::$app->config->set($originalSecurity, 'security');
        }
    }

    public function test_admin_login_lockout_message_tracks_configured_lockout_duration(): void
    {
        $originalSecurity = self::$app->config->get('security');
        $security = is_array($originalSecurity) ? $originalSecurity : [];
        $security['login'] = array_merge($security['login'] ?? [], [
            'lockout_time' => 1800,
        ]);
        self::$app->config->set($security, 'security');

        $request = (clone self::$app->request)
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->withPost([
                'user' => 'admin',
                'pass' => 'bad-password',
            ])
            ->setMethod('POST');

        self::$app->instance('request', $request);

        $originalLimiter = self::$app->make(LoginAttemptLimiter::class);
        self::$app->instance(LoginAttemptLimiter::class, new class extends LoginAttemptLimiter {
            public function tooManyLoginAttempts(string $clientIp): bool
            {
                return true;
            }
        });

        try {
            $controller = new AdminAuthController(self::$app);
            $response = $controller->login();
            $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(-1, $payload['code']);
            $this->assertSame('登录失败次数过多，请30分钟后重试', $payload['msg']);
        } finally {
            self::$app->instance(LoginAttemptLimiter::class, $originalLimiter);
            self::$app->config->set($originalSecurity, 'security');
        }
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

    public function test_admin_and_monitor_sources_avoid_risky_legacy_patterns(): void
    {
        $adminSource = (string) file_get_contents(self::$rootPath . 'app/controller/Admin.php');
        $monitorSource = (string) file_get_contents(self::$rootPath . 'app/service/MonitorService.php');
        $installStateSource = (string) file_get_contents(self::$rootPath . 'app/service/install/InstallStateService.php');

        $this->assertStringNotContainsString('executeShellCommand(', $adminSource);
        $this->assertStringNotContainsString('SELECT VERSION()', $adminSource);
        $this->assertStringNotContainsString('<font', $adminSource);
        $this->assertStringNotContainsString('TmpPrice::select()', $monitorSource);
        $this->assertStringContainsString('whereNotIn', $monitorSource);
        $this->assertStringContainsString('whereNotNull', $monitorSource);
        $this->assertStringNotContainsString('SHOW TABLES LIKE', $installStateSource);
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

    // -----------------------------------------------------------------------
    // Security regression: XSS fix (#1)
    // -----------------------------------------------------------------------

    public function test_create_order_html_path_uses_redirect_not_inline_script(): void
    {
        $source = (string) file_get_contents(self::$rootPath . 'app/controller/merchant/Order.php');

        $this->assertStringNotContainsString(
            '<script>window.location.href',
            $source,
            'XSS fix: createOrder must not echo an inline <script> redirect.'
        );
        $this->assertStringContainsString(
            'return redirect(',
            $source,
            'XSS fix: createOrder must use a server-side redirect for the isHtml path.'
        );
        $this->assertStringContainsString(
            'rawurlencode(',
            $source,
            'XSS fix: orderId must be URL-encoded before being placed in the redirect target.'
        );
    }

    public function test_create_order_ishtml_cast_to_int_before_comparison(): void
    {
        $source = (string) file_get_contents(self::$rootPath . 'app/controller/merchant/Order.php');

        $this->assertStringContainsString(
            '$isHtml = (int)',
            $source,
            'isHtml must be cast to int to prevent loose-comparison bypass.'
        );
        $this->assertStringNotContainsString(
            '$isHtml = $params[',
            $source,
            'isHtml must not be assigned raw from $params without a cast.'
        );
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
