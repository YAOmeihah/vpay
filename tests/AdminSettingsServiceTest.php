<?php
declare(strict_types=1);

namespace tests;

use app\model\MonitorTerminal;
use app\model\Setting;
use app\service\admin\ChannelAdminService;
use app\service\admin\AdminSettingsService;
use app\service\admin\TerminalAdminService;

class AdminSettingsServiceTest extends TestCase
{
    public function test_get_settings_generates_distinct_sign_and_monitor_keys_when_missing(): void
    {
        $this->seedSettings([
            'key' => '',
            'monitorKey' => '',
        ]);
        MonitorTerminal::where('terminal_code', 'legacy-default')->update(['monitor_key' => '']);

        $service = new AdminSettingsService();

        $settings = $service->getSettings();

        $this->assertNotSame('', $settings['key']);
        $this->assertNotSame('', $settings['monitorKey']);
        $this->assertNotSame($settings['key'], $settings['monitorKey']);
        $this->assertSame($settings['key'], Setting::getConfigValue('key'));
        $this->assertSame($settings['monitorKey'], Setting::getConfigValue('monitorKey'));
    }

    public function test_get_settings_keeps_legacy_monitor_fields_backed_by_default_terminal(): void
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
                    'lastheart' => '1713888000',
                    'lastpay' => '1713889000',
                    'jkstate' => '1',
                    default => $default,
                };
            }

            protected function terminalAdminService(): TerminalAdminService
            {
                return new class extends TerminalAdminService {
                    public function legacyDefaultMonitorKey(): string
                    {
                        return 'terminal-key';
                    }
                };
            }

            protected function channelAdminService(): ChannelAdminService
            {
                return new class extends ChannelAdminService {
                    public function legacyDefaultPair(): array
                    {
                        return [
                            'wxpay' => 'weixin://pay/default',
                            'zfbpay' => 'alipay://pay/default',
                        ];
                    }
                };
            }
        };

        $settings = $service->getSettings();

        $this->assertSame('terminal-key', $settings['monitorKey']);
        $this->assertSame('weixin://pay/default', $settings['wxpay']);
        $this->assertSame('alipay://pay/default', $settings['zfbpay']);
        $this->assertSame('round_robin', $settings['allocationStrategy']);
    }
}
