<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\admin\AdminSettingsService;

class AdminSettingsServiceTest extends TestCase
{
    public function test_get_settings_generates_distinct_sign_and_monitor_keys_when_missing(): void
    {
        $this->seedSettings([
            'key' => '',
            'monitorKey' => '',
        ]);

        $service = new AdminSettingsService();

        $settings = $service->getSettings();

        $this->assertNotSame('', $settings['key']);
        $this->assertNotSame('', $settings['monitorKey']);
        $this->assertNotSame($settings['key'], $settings['monitorKey']);
        $this->assertSame($settings['key'], Setting::getConfigValue('key'));
        $this->assertSame($settings['monitorKey'], Setting::getConfigValue('monitorKey'));
    }
}
