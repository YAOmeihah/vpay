<?php
declare(strict_types=1);

namespace tests;

use app\service\install\InstallStateService;

final class InstallStateServiceTest extends TestCase
{
    private string $runtimeDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runtimeDir = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'vpay-install-state-'
            . substr(sha1((string) microtime(true) . (string) mt_rand()), 0, 12);

        @mkdir($this->runtimeDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_file($this->runtimeDir . DIRECTORY_SEPARATOR . 'enable.flag')) {
            @unlink($this->runtimeDir . DIRECTORY_SEPARATOR . 'enable.flag');
        }
        if (is_file($this->runtimeDir . DIRECTORY_SEPARATOR . 'lock.json')) {
            @unlink($this->runtimeDir . DIRECTORY_SEPARATOR . 'lock.json');
        }
        if (is_file($this->runtimeDir . DIRECTORY_SEPARATOR . 'last-error.json')) {
            @unlink($this->runtimeDir . DIRECTORY_SEPARATOR . 'last-error.json');
        }
        @rmdir($this->runtimeDir);

        parent::tearDown();
    }

    public function test_reports_not_installed_when_enable_flag_exists_and_install_status_is_missing(): void
    {
        $service = new class($this->runtimeDir) extends InstallStateService {
            public function __construct(private readonly string $runtimeDir)
            {
            }

            protected function installRuntimePath(): string
            {
                return $this->runtimeDir;
            }
        };

        file_put_contents($this->runtimeDir . DIRECTORY_SEPARATOR . 'enable.flag', '1');

        self::assertSame('not_installed', $service->status()['state']);
    }

    public function test_reports_upgrade_required_when_schema_version_is_older_than_app_version(): void
    {
        $this->seedSettings([
            'install_status' => 'installed',
            'schema_version' => '1.9.9',
            'app_version' => '1.9.9',
        ]);

        $service = new InstallStateService();

        self::assertSame('upgrade_required', $service->status()['state']);
    }
}
