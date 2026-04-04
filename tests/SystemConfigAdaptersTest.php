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
            'close' => '30',
            'payQf' => '2',
            'wxpay' => 'weixin://custom-pay-url',
            'zfbpay' => 'alipays://custom-pay-url',
            'notify_ssl_verify' => '0',
            'epay_enabled' => '1',
            'epay_pid' => ' 20002 ',
            'epay_key' => '  custom-epay-key  ',
            'epay_name' => ' 自定义订单支付 ',
            'epay_private_key' => '  custom-private-key  ',
            'epay_public_key' => '  custom-public-key  ',
        ]);

        $config = new SettingSystemConfig();

        $this->assertSame('https://merchant.example/custom-notify', $config->getNotifyUrl());
        $this->assertSame('https://merchant.example/custom-return', $config->getReturnUrl());
        $this->assertSame('custom-sign-key', $config->getSignKey());
        $this->assertSame(30, $config->getOrderCloseMinutes());
        $this->assertSame('2', $config->getPayQfMode());
        $this->assertSame('weixin://custom-pay-url', $config->getWeChatPayUrl());
        $this->assertSame('alipays://custom-pay-url', $config->getAlipayPayUrl());
        $this->assertFalse($config->getNotifySslVerifyEnabled());
        $this->assertSame([
            'enabled' => true,
            'pid' => '20002',
            'key' => 'custom-epay-key',
            'name' => '自定义订单支付',
            'private_key' => 'custom-private-key',
            'public_key' => 'custom-public-key',
        ], $config->getEpayConfig());

        Setting::where('vkey', 'notify_ssl_verify')->delete();
        CacheService::deleteSetting('notify_ssl_verify');
        Setting::where('vkey', 'epay_enabled')->delete();
        CacheService::deleteSetting('epay_enabled');
        Setting::where('vkey', 'epay_name')->delete();
        CacheService::deleteSetting('epay_name');

        $defaultConfig = new SettingSystemConfig();

        $this->assertTrue($defaultConfig->getNotifySslVerifyEnabled());
        $this->assertFalse($defaultConfig->getEpayConfig()['enabled']);
        $this->assertSame('订单支付', $defaultConfig->getEpayConfig()['name']);
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
