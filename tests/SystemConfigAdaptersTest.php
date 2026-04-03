<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
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
            'epay_pid' => '20002',
            'epay_key' => 'custom-epay-key',
            'epay_name' => '自定义订单支付',
            'epay_private_key' => 'custom-private-key',
            'epay_public_key' => 'custom-public-key',
        ]);

        $config = new SettingSystemConfig();

        $this->assertSame('https://merchant.example/custom-notify', $config->getNotifyUrl());
        $this->assertSame('https://merchant.example/custom-return', $config->getReturnUrl());
        $this->assertSame('custom-sign-key', $config->getSignKey());
        $this->assertSame(30, $config->getCloseMinutes());
        $this->assertSame(2, $config->getPayQfMode());
        $this->assertSame('weixin://custom-pay-url', $config->getWeChatPayUrl());
        $this->assertSame('alipays://custom-pay-url', $config->getAlipayPayUrl());
        $this->assertFalse($config->shouldVerifyNotifySsl());
        $this->assertSame([
            'enabled' => true,
            'pid' => '20002',
            'key' => 'custom-epay-key',
            'name' => '自定义订单支付',
            'private_key' => 'custom-private-key',
            'public_key' => 'custom-public-key',
        ], $config->getEpayConfig());
    }

    public function test_setting_monitor_state_reads_and_updates_runtime_flags(): void
    {
        $this->seedSettings([
            'lastheart' => '1700000000',
            'lastpay' => '1700000100',
            'jkstate' => '1',
        ]);

        $state = new SettingMonitorState();

        $this->assertSame(1700000000, $state->getLastHeartbeat());
        $this->assertSame(1700000100, $state->getLastPayTime());
        $this->assertTrue($state->isOnline());

        $state->setLastHeartbeat(1700000200);
        $state->setLastPayTime(1700000300);
        $state->setOnline(false);

        $this->assertSame(1700000200, $state->getLastHeartbeat());
        $this->assertSame(1700000300, $state->getLastPayTime());
        $this->assertFalse($state->isOnline());
        $this->assertSame('1700000200', Setting::getConfigValue('lastheart'));
        $this->assertSame('1700000300', Setting::getConfigValue('lastpay'));
        $this->assertSame('0', Setting::getConfigValue('jkstate'));
    }
}
