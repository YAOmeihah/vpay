<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\install\AdminBootstrapService;
use app\service\install\EnvWriter;

final class InstallBootstrapServicesTest extends TestCase
{
    public function test_admin_bootstrap_overwrites_placeholder_admin_and_generates_sign_key(): void
    {
        Setting::setConfigValue('user', 'admin');
        Setting::setConfigValue('pass', '$2y$10$placeholder');
        Setting::setConfigValue('key', '');

        $service = new AdminBootstrapService();
        $service->bootstrap([
            'admin_user' => 'owner',
            'admin_pass' => 'owner-password-123',
            'schema_version' => '2.1.0',
            'app_version' => '2.1.0',
        ]);

        self::assertSame('owner', Setting::getConfigValue('user'));
        self::assertTrue(password_verify('owner-password-123', Setting::getConfigValue('pass')));
        self::assertNotSame('', Setting::getConfigValue('key'));
        self::assertSame('installed', Setting::getConfigValue('install_status'));
        self::assertSame('2.1.0', Setting::getConfigValue('schema_version'));
        self::assertSame('2.1.0', Setting::getConfigValue('app_version'));
    }

    public function test_env_writer_returns_manual_copy_payload_when_target_is_not_writable(): void
    {
        $writer = new class extends EnvWriter {
            protected function writeTarget(string $path, string $content): bool
            {
                return false;
            }
        };

        $result = $writer->write([
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'vmqphp8',
        ]);

        self::assertFalse($result['written']);
        self::assertStringContainsString('DB_HOST = 127.0.0.1', $result['content']);
        self::assertStringContainsString('DB_NAME = vmqphp8', $result['content']);
    }
}
