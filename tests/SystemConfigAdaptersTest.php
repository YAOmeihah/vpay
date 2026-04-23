<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\CacheService;
use app\service\config\SettingSystemConfig;

class SystemConfigAdaptersTest extends TestCase
{
    public function test_setting_system_config_exposes_typed_accessors(): void
    {
        $this->seedSettings([
            'notifyUrl' => 'https://merchant.example/custom-notify',
            'returnUrl' => 'https://merchant.example/custom-return',
            'key' => 'custom-sign-key',
            'close' => '30',
            'payQf' => '2',
            'notify_ssl_verify' => '0',
        ]);

        $config = new SettingSystemConfig();

        $this->assertSame('https://merchant.example/custom-notify', $config->getNotifyUrl());
        $this->assertSame('https://merchant.example/custom-return', $config->getReturnUrl());
        $this->assertSame('custom-sign-key', $config->getSignKey());
        $this->assertSame(30, $config->getOrderCloseMinutes());
        $this->assertSame('2', $config->getPayQfMode());
        $this->assertFalse($config->getNotifySslVerifyEnabled());

        Setting::where('vkey', 'notify_ssl_verify')->delete();
        CacheService::deleteSetting('notify_ssl_verify');

        $defaultConfig = new SettingSystemConfig();

        $this->assertTrue($defaultConfig->getNotifySslVerifyEnabled());
    }
}
