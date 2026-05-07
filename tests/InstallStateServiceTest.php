<?php
declare(strict_types=1);

namespace tests;

use app\service\CacheService;
use app\service\install\InstallStateService;
use think\facade\Db;

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

    public function test_reports_not_installed_for_release_package_without_env_file(): void
    {
        $envPath = $this->runtimeDir . DIRECTORY_SEPARATOR . '.env';
        $service = new class($this->runtimeDir, $envPath) extends InstallStateService {
            public function __construct(
                private readonly string $runtimeDir,
                private readonly string $envPath
            ) {
            }

            protected function installRuntimePath(): string
            {
                return $this->runtimeDir;
            }

            protected function settingsTableAvailable(): bool
            {
                return false;
            }

            protected function envFilePath(): string
            {
                return $this->envPath;
            }
        };

        self::assertSame('not_installed', $service->status()['state']);
    }

    public function test_keeps_installer_closed_when_env_exists_but_settings_table_is_unavailable(): void
    {
        $envPath = $this->runtimeDir . DIRECTORY_SEPARATOR . '.env';
        file_put_contents($envPath, 'DB_NAME = vmqphp8');
        $service = new class($this->runtimeDir, $envPath) extends InstallStateService {
            public function __construct(
                private readonly string $runtimeDir,
                private readonly string $envPath
            ) {
            }

            protected function installRuntimePath(): string
            {
                return $this->runtimeDir;
            }

            protected function settingsTableAvailable(): bool
            {
                return false;
            }

            protected function envFilePath(): string
            {
                return $this->envPath;
            }
        };

        self::assertSame('installed', $service->status()['state']);
    }

    public function test_reports_upgrade_required_when_schema_version_is_older_than_app_version(): void
    {
        $this->seedSettings([
            'install_status' => 'installed',
            'schema_version' => '1.9.9',
            'app_version' => '1.9.9',
        ]);

        $service = new class($this->runtimeDir) extends InstallStateService {
            public function __construct(private readonly string $runtimeDir)
            {
            }

            protected function installRuntimePath(): string
            {
                return $this->runtimeDir;
            }
        };

        self::assertSame('upgrade_required', $service->status()['state']);
    }

    public function test_reports_upgrade_required_when_current_version_has_unfinished_migrations(): void
    {
        $migrationPath = app()->getRootPath() . 'database/migrations/2.1.14/999-state-detection-test.sql';
        file_put_contents($migrationPath, "SELECT 1;\n");

        try {
            $this->seedSettings([
                'install_status' => 'installed',
                'schema_version' => '2.1.14',
                'app_version' => '2.1.14',
            ]);

            Db::execute('DROP TABLE IF EXISTS `system_migration_log`');
            Db::execute((string) file_get_contents(app()->getRootPath() . 'database/migrations/2.1.0/001-create-system-migration-log.sql'));

            $scanner = new \app\service\install\MigrationScanner();
            foreach ($scanner->upTo('2.1.14') as $migration) {
                if ($migration['migration_key'] === '2.1.14/999-state-detection-test.sql') {
                    continue;
                }

                Db::name('system_migration_log')->insert([
                    'migration_key' => (string) $migration['migration_key'],
                    'from_version' => '2.1.14',
                    'to_version' => (string) $migration['version'],
                    'status' => 'finished',
                    'started_at' => time(),
                    'finished_at' => time(),
                    'error_message' => '',
                    'checksum' => sha1((string) file_get_contents((string) $migration['path'])),
                ]);
            }

            $service = new class($this->runtimeDir) extends InstallStateService {
                public function __construct(private readonly string $runtimeDir)
                {
                }

                protected function installRuntimePath(): string
                {
                    return $this->runtimeDir;
                }
            };

            $status = $service->status();

            self::assertSame('upgrade_required', $status['state']);
            self::assertSame('2.1.14', $status['current_version']);
            self::assertSame('2.1.15', $status['target_version']);
        } finally {
            @unlink($migrationPath);
            Db::execute('DROP TABLE IF EXISTS `system_migration_log`');
        }
    }

    public function test_reports_upgrade_required_for_legacy_installed_database_without_lifecycle_keys(): void
    {
        $this->seedSettings([
            'user' => 'admin',
            'pass' => '$2y$10$legacy-placeholder',
            'key' => 'legacy-sign-key',
            'notify_ssl_verify' => '1',
        ]);

        $service = new class($this->runtimeDir) extends InstallStateService {
            public function __construct(private readonly string $runtimeDir)
            {
            }

            protected function installRuntimePath(): string
            {
                return $this->runtimeDir;
            }
        };

        $status = $service->status();

        self::assertSame('upgrade_required', $status['state']);
        self::assertSame('检测到旧版系统，需要升级', $status['message']);
        self::assertSame('2.0.0', $status['current_version']);
    }

    public function test_state_detection_ignores_stale_setting_cache_values(): void
    {
        Db::execute('DELETE FROM `setting`');
        Db::name('setting')->insertAll([
            ['vkey' => 'user', 'vvalue' => 'legacy-admin'],
            ['vkey' => 'pass', 'vvalue' => password_hash('legacy-pass', PASSWORD_DEFAULT)],
            ['vkey' => 'key', 'vvalue' => 'legacy-key'],
        ]);
        CacheService::cacheSetting('install_status', 'installed');
        CacheService::cacheSetting('schema_version', '2.1.0');

        $service = new class($this->runtimeDir) extends InstallStateService {
            public function __construct(private readonly string $runtimeDir)
            {
            }

            protected function installRuntimePath(): string
            {
                return $this->runtimeDir;
            }
        };

        $status = $service->status();

        self::assertSame('upgrade_required', $status['state']);
        self::assertSame('2.0.0', $status['current_version']);
    }
}
