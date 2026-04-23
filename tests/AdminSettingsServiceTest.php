<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\admin\AdminSettingsService;

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
        $this->assertSame($settings['key'], Setting::getConfigValue('key'));
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
}
