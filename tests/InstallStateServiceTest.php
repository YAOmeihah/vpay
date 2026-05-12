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

    public function test_stale_last_error_does_not_override_installed_state(): void
    {
        file_put_contents($this->runtimeDir . DIRECTORY_SEPARATOR . 'last-error.json', '{"step":"old","message":"旧错误"}');
        $this->seedSettings([
            'install_status' => 'installed',
            'schema_version' => '2.1.17',
            'app_version' => '2.1.17',
        ]);
        $this->markAllMigrationsFinished('2.1.17');

        $service = new class($this->runtimeDir) extends InstallStateService {
            public function __construct(private readonly string $runtimeDir)
            {
            }

            protected function installRuntimePath(): string
            {
                return $this->runtimeDir;
            }
        };

        self::assertSame('installed', $service->status()['state']);
    }

    public function test_stale_last_error_does_not_override_upgrade_required_state(): void
    {
        file_put_contents($this->runtimeDir . DIRECTORY_SEPARATOR . 'last-error.json', '{"step":"old","message":"旧错误"}');
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

        $status = $service->status();

        self::assertSame('upgrade_required', $status['state']);
        self::assertSame('1.9.9', $status['current_version']);
    }

    public function test_reports_upgrade_required_when_current_version_has_unfinished_migrations(): void
    {
        $migrationName = '2026_05_11_100004_state_detection_test';
        $migrationPath = app()->getRootPath() . 'database/migrations/' . $migrationName . '.sql';
        file_put_contents($migrationPath, "SELECT 1;\n");

        try {
            $this->seedSettings([
                'install_status' => 'installed',
                'schema_version' => '2.1.17',
                'app_version' => '2.1.17',
            ]);

            Db::execute('DROP TABLE IF EXISTS `system_migration_log`');
            app()->make(\app\service\install\MigrationLogService::class)->ensureTable();

            $scanner = new \app\service\install\MigrationScanner();
            foreach ($scanner->upTo('2.1.17') as $migration) {
                if ($migration['migration_key'] === $migrationName) {
                    continue;
                }

                Db::name('system_migration_log')->insert([
                    'migration_key' => (string) $migration['migration_key'],
                    'from_version' => '2.1.17',
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
            self::assertSame('2.1.17', $status['current_version']);
            self::assertSame('2.1.17', $status['target_version']);
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

    private function markAllMigrationsFinished(string $version): void
    {
        app()->make(\app\service\install\MigrationLogService::class)->ensureTable();
        Db::execute('DELETE FROM `system_migration_log`');

        foreach ((new \app\service\install\MigrationScanner())->upTo($version) as $migration) {
            Db::name('system_migration_log')->insert([
                'migration_key' => (string) $migration['migration_key'],
                'from_version' => $version,
                'to_version' => (string) $migration['version'],
                'status' => 'finished',
                'started_at' => time(),
                'finished_at' => time(),
                'error_message' => '',
                'checksum' => sha1((string) file_get_contents((string) $migration['path'])),
            ]);
        }
    }
}
