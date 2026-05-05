<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\admin\AdminSettingsService;
use app\service\security\KeyEncryptionService;

class AdminSettingsServiceTest extends TestCase
{
    public function test_get_settings_generates_sign_key_when_missing_without_global_monitor_key(): void
    {
        $this->seedSettings([
            'key' => '',
        ]);

        $service = new AdminSettingsService();

        $settings = $service->getSettings();

        $this->assertNotSame('', $settings['key']);
        $this->assertArrayNotHasKey('monitorKey', $settings);
        $storedKey = Setting::getConfigValue('key');
        $this->assertStringStartsWith('enc:', $storedKey);
        $this->assertSame($settings['key'], (new KeyEncryptionService())->decrypt($storedKey));
    }

    public function test_get_settings_keeps_only_global_payment_and_security_fields(): void
    {
        $service = new class extends AdminSettingsService {
            protected function getConfigValue(string $key, string $default = ''): string
            {
                return match ($key) {
                    'user' => 'admin',
                    'notifyUrl' => 'https://merchant.example/notify',
                    'returnUrl' => 'https://merchant.example/return',
                    'key' => 'merchant-key',
                    'notify_ssl_verify' => '1',
                    'close' => '15',
                    'payQf' => '1',
                    'allocationStrategy' => 'round_robin',
                    default => $default,
                };
            }
        };

        $settings = $service->getSettings();

        $this->assertArrayNotHasKey('monitorKey', $settings);
        $this->assertArrayNotHasKey('wxpay', $settings);
        $this->assertArrayNotHasKey('zfbpay', $settings);
        $this->assertArrayNotHasKey('jkstate', $settings);
        $this->assertSame('round_robin', $settings['allocationStrategy']);
    }

    public function test_save_settings_rejects_invalid_allocation_strategy(): void
    {
        $service = new AdminSettingsService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('分配策略无效');

        $service->saveSettings([
            'allocationStrategy' => 'unexpected_strategy',
        ]);
    }

    public function test_get_settings_returns_maintenance_defaults_disabled_by_default(): void
    {
        $settings = (new AdminSettingsService())->getSettings();

        self::assertSame('0', $settings['maintenance_enabled']);
        self::assertSame('', $settings['maintenance_token']);
        self::assertSame('', $settings['maintenance_allowed_ips']);
        self::assertSame('1', $settings['maintenance_task_terminal_offline_check']);
        self::assertSame('1', $settings['maintenance_task_expired_order_cleanup']);
        self::assertSame('', $settings['maintenance_last_run_at']);
        self::assertSame('', $settings['maintenance_last_run_result']);
        self::assertSame('0', $settings['notify_telegram_enabled']);
        self::assertSame('', $settings['notify_telegram_bot_token']);
        self::assertSame('', $settings['notify_telegram_chat_id']);
        self::assertSame('1', $settings['notify_event_terminal_offline']);
        self::assertSame('1', $settings['notify_event_terminal_recovered']);
        self::assertSame('1', $settings['notify_event_expired_order_cleanup']);
        self::assertSame('1', $settings['notify_event_maintenance_exception']);
    }

    public function test_save_settings_persists_maintenance_fields(): void
    {
        $service = new AdminSettingsService();

        $service->saveSettings([
            'maintenance_enabled' => '1',
            'maintenance_token' => 'cron-token',
            'maintenance_allowed_ips' => "127.0.0.1, 10.0.0.2\n",
            'maintenance_task_terminal_offline_check' => '0',
            'maintenance_task_expired_order_cleanup' => '1',
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot-token',
            'notify_telegram_chat_id' => '123456',
            'notify_event_terminal_offline' => '1',
            'notify_event_terminal_recovered' => '0',
            'notify_event_expired_order_cleanup' => '1',
            'notify_event_maintenance_exception' => '0',
        ]);

        self::assertSame('1', Setting::getConfigValue('maintenance_enabled'));
        self::assertSame('cron-token', Setting::getConfigValue('maintenance_token'));
        self::assertSame('127.0.0.1,10.0.0.2', Setting::getConfigValue('maintenance_allowed_ips'));
        self::assertSame('0', Setting::getConfigValue('maintenance_task_terminal_offline_check'));
        self::assertSame('1', Setting::getConfigValue('maintenance_task_expired_order_cleanup'));
        self::assertSame('1', Setting::getConfigValue('notify_telegram_enabled'));
        self::assertSame('bot-token', Setting::getConfigValue('notify_telegram_bot_token'));
        self::assertSame('123456', Setting::getConfigValue('notify_telegram_chat_id'));
        self::assertSame('0', Setting::getConfigValue('notify_event_terminal_recovered'));
        self::assertSame('0', Setting::getConfigValue('notify_event_maintenance_exception'));
    }

    public function test_generate_maintenance_token_persists_random_hex_token(): void
    {
        $service = new AdminSettingsService();

        $token = $service->generateMaintenanceToken();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame($token, Setting::getConfigValue('maintenance_token'));
    }

    public function test_admin_routes_include_maintenance_actions(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__) . '/route/admin.php');
        $controller = (string) file_get_contents(dirname(__DIR__) . '/app/controller/admin/Settings.php');

        self::assertStringContainsString(
            "Route::post('generateMaintenanceToken', 'admin.Settings/generateMaintenanceToken')",
            $routes
        );
        self::assertStringContainsString(
            "Route::post('testMaintenanceNotification', 'admin.Settings/testMaintenanceNotification')",
            $routes
        );
        self::assertStringContainsString('function generateMaintenanceToken()', $controller);
        self::assertStringContainsString('function testMaintenanceNotification()', $controller);
    }
}
