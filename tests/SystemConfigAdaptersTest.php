<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\CacheService;
use app\service\config\SettingSystemConfig;
use app\service\runtime\SettingMonitorState;

class SystemConfigAdaptersTest extends TestCase
{
    public function test_setting_system_config_exposes_typed_accessors(): void
    {
        $this->seedSettings([
            'notifyUrl' => 'https://merchant.example/custom-notify',
            'returnUrl' => 'https://merchant.example/custom-return',
            'key' => 'custom-sign-key',
            'monitorKey' => 'custom-monitor-key',
            'close' => '30',
            'payQf' => '2',
            'wxpay' => 'weixin://custom-pay-url',
            'zfbpay' => 'alipays://custom-pay-url',
            'notify_ssl_verify' => '0',
        ]);

        $config = new SettingSystemConfig();

        $this->assertSame('https://merchant.example/custom-notify', $config->getNotifyUrl());
        $this->assertSame('https://merchant.example/custom-return', $config->getReturnUrl());
        $this->assertSame('custom-sign-key', $config->getSignKey());
        $this->assertSame('custom-monitor-key', $config->getMonitorSignKey());
        $this->assertSame(30, $config->getOrderCloseMinutes());
        $this->assertSame('2', $config->getPayQfMode());
        $this->assertSame('weixin://custom-pay-url', $config->getWeChatPayUrl());
        $this->assertSame('alipays://custom-pay-url', $config->getAlipayPayUrl());
        $this->assertFalse($config->getNotifySslVerifyEnabled());

        Setting::where('vkey', 'notify_ssl_verify')->delete();
        CacheService::deleteSetting('notify_ssl_verify');

        $defaultConfig = new SettingSystemConfig();

        $this->assertTrue($defaultConfig->getNotifySslVerifyEnabled());
    }

    public function test_setting_monitor_state_reads_and_updates_runtime_flags(): void
    {
        $this->seedSettings([
            'lastheart' => '1700000000',
            'lastpay' => '1700000100',
            'jkstate' => '1',
        ]);

        $state = new SettingMonitorState();

        $this->assertSame(1700000000, $state->getLastHeartbeatAt());
        $this->assertSame(1700000100, $state->getLastPaidAt());
        $this->assertTrue($state->isOnline());

        $state->markHeartbeatAt(1700000200);
        $state->markPaidAt(1700000300);
        $state->markOffline();

        $this->assertSame(1700000200, $state->getLastHeartbeatAt());
        $this->assertSame(1700000300, $state->getLastPaidAt());
        $this->assertFalse($state->isOnline());
        $this->assertSame('1700000200', Setting::getConfigValue('lastheart'));
        $this->assertSame('1700000300', Setting::getConfigValue('lastpay'));
        $this->assertSame('0', Setting::getConfigValue('jkstate'));

        $state->markOnline();

        $this->assertTrue($state->isOnline());
        $this->assertSame('1', Setting::getConfigValue('jkstate'));
    }
}
