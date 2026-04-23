<?php
declare(strict_types=1);

namespace tests;

use app\service\SignService;
use app\service\admin\AdminSettingsService;
use app\service\terminal\TerminalCredentialService;

class SingleTerminalRemovalTest extends TestCase
{
    public function test_admin_settings_no_longer_exposes_single_terminal_monitor_or_default_qrcode_config(): void
    {
        $settings = (new AdminSettingsService())->getSettings();

        foreach (['monitorKey', 'lastheart', 'lastpay', 'jkstate', 'wxpay', 'zfbpay'] as $key) {
            $this->assertArrayNotHasKey($key, $settings);
        }
    }

    public function test_terminal_credentials_require_an_explicit_terminal_code(): void
    {
        $service = new TerminalCredentialService(
            lookupByCode: static fn (string $code) => null,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('终端编码不能为空');

        $service->requireTerminal('');
    }

    public function test_legacy_monitor_signature_methods_are_removed(): void
    {
        $this->assertFalse(method_exists(SignService::class, 'verifyMonitorSimpleSign'));
        $this->assertFalse(method_exists(SignService::class, 'verifyMonitorPushSign'));
    }

    public function test_monitor_controller_no_longer_accepts_missing_terminal_code(): void
    {
        $source = file_get_contents(root_path() . 'app/controller/monitor/Monitor.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString('verifyMonitorSimpleSignature', $source);
        $this->assertStringNotContainsString('verifyMonitorPushSignature', $source);
        $this->assertStringNotContainsString('handlePayPush(', $source);
        $this->assertStringNotContainsString('SettingMonitorState', $source);
    }
}
