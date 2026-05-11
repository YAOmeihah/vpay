<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\install\MigrationLogService;
use app\service\install\MigrationRunner;
use app\service\install\MigrationScanner;
use think\facade\Db;

final class MigrationRunnerTest extends TestCase
{
    private const PAYMENT_SUCCESS_MIGRATION = '2026_05_11_090000_ensure_payment_success_notification_settings';

    public function test_scanner_returns_laravel_style_root_migrations_in_filename_order(): void
    {
        $firstPath = $this->migrationPath('2026_05_11_080000_alpha_test');
        $lastPath = $this->migrationPath('2026_05_11_100000_zeta_test');
        $invalidPath = app()->getRootPath() . 'database/migrations/20260511070000-invalid-test.sql';

        file_put_contents($firstPath, "SELECT 1;\n");
        file_put_contents($lastPath, "SELECT 1;\n");
        file_put_contents($invalidPath, "SELECT 1;\n");

        try {
            $scanner = new MigrationScanner();
            $files = $scanner->upTo('2.1.17');
            $paths = array_map(static fn (array $item): string => $item['relative_path'], $files);

            foreach ($paths as $path) {
                self::assertMatchesRegularExpression(
                    '#^database/migrations/\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.sql$#i',
                    $path
                );
            }

            self::assertContains('database/migrations/2026_05_11_080000_alpha_test.sql', $paths);
            self::assertContains(
                'database/migrations/' . self::PAYMENT_SUCCESS_MIGRATION . '.sql',
                $paths
            );
            self::assertContains('database/migrations/2026_05_11_100000_zeta_test.sql', $paths);
            self::assertNotContains('database/migrations/20260511070000-invalid-test.sql', $paths);

            self::assertSame($paths, array_values($paths));
            self::assertLessThan(
                array_search('database/migrations/' . self::PAYMENT_SUCCESS_MIGRATION . '.sql', $paths, true),
                array_search('database/migrations/2026_05_11_080000_alpha_test.sql', $paths, true)
            );

            $byPath = array_column($files, null, 'relative_path');
            self::assertSame(
                self::PAYMENT_SUCCESS_MIGRATION,
                $byPath['database/migrations/' . self::PAYMENT_SUCCESS_MIGRATION . '.sql']['migration_key'] ?? ''
            );
        } finally {
            @unlink($firstPath);
            @unlink($lastPath);
            @unlink($invalidPath);
        }
    }

    public function test_runner_executes_pending_migrations_and_updates_schema_version(): void
    {
        Db::execute('DROP TABLE IF EXISTS `system_migration_log`');
        Setting::setConfigValue('schema_version', '2.1.16');
        Setting::setConfigValue('app_version', '2.1.16');
        Setting::setConfigValue('install_status', 'installed');

        $runner = new MigrationRunner();
        $runner->runPending('2.1.16', '2.1.17');

        self::assertSame('2.1.17', Setting::getConfigValue('schema_version'));
        self::assertSame('2.1.17', Setting::getConfigValue('app_version'));
        self::assertSame('1', Setting::getConfigValue('notify_event_payment_success'));
        self::assertSame('1', Setting::getConfigValue('notify_payment_success_callback_status'));

        $row = Db::name('system_migration_log')
            ->where('migration_key', self::PAYMENT_SUCCESS_MIGRATION)
            ->find();
        self::assertSame('finished', $row['status'] ?? null);
    }

    public function test_runner_updates_versions_when_all_migrations_are_finished(): void
    {
        Setting::setConfigValue('schema_version', '2.1.16');
        Setting::setConfigValue('app_version', '2.1.16');
        Setting::setConfigValue('install_status', 'installed');
        $this->markAllMigrationsFinished('2.1.16');

        $runner = new MigrationRunner();
        $runner->runPending('2.1.16', '2.1.17');

        self::assertSame('2.1.17', Setting::getConfigValue('schema_version'));
        self::assertSame('2.1.17', Setting::getConfigValue('app_version'));
    }

    public function test_runner_executes_unfinished_migration_when_schema_version_matches_target(): void
    {
        $migrationName = '2026_05_11_100001_log_aware_test';
        $migrationPath = $this->migrationPath($migrationName);
        file_put_contents(
            $migrationPath,
            "INSERT INTO `setting` (`vkey`, `vvalue`) VALUES ('log_aware_migration', 'ran')"
            . " ON DUPLICATE KEY UPDATE `vvalue` = VALUES(`vvalue`);\n"
        );

        try {
            Setting::setConfigValue('schema_version', '2.1.17');
            Setting::setConfigValue('app_version', '2.1.17');
            Setting::setConfigValue('install_status', 'installed');
            $this->markAllMigrationsFinished('2.1.17', [$migrationName]);

            $runner = new MigrationRunner();
            $runner->runPending('2.1.17', '2.1.17');

            self::assertSame('ran', Setting::getConfigValue('log_aware_migration'));
        } finally {
            @unlink($migrationPath);
        }
    }

    public function test_runner_skips_finished_migration_log_entries(): void
    {
        $migrationName = '2026_05_11_100002_log_aware_skip_test';
        $migrationPath = $this->migrationPath($migrationName);
        file_put_contents(
            $migrationPath,
            "UPDATE `setting` SET `vvalue` = 'changed' WHERE `vkey` = 'log_aware_skip';\n"
        );

        try {
            Setting::setConfigValue('schema_version', '2.1.17');
            Setting::setConfigValue('app_version', '2.1.17');
            Setting::setConfigValue('install_status', 'installed');
            Setting::setConfigValue('log_aware_skip', 'keep');
            $this->markAllMigrationsFinished('2.1.17');

            $runner = new MigrationRunner();
            $runner->runPending('2.1.17', '2.1.17');

            self::assertSame('keep', Setting::getConfigValue('log_aware_skip'));
        } finally {
            @unlink($migrationPath);
        }
    }

    public function test_runner_skips_add_column_migration_when_column_already_exists(): void
    {
        $migrationName = '2026_05_11_100003_add_existing_setting_column_test';
        $migrationPath = $this->migrationPath($migrationName);
        file_put_contents(
            $migrationPath,
            "ALTER TABLE `setting` ADD COLUMN `vkey` varchar(100) NOT NULL;\n"
        );

        try {
            Setting::setConfigValue('schema_version', '2.1.17');
            Setting::setConfigValue('app_version', '2.1.17');
            Setting::setConfigValue('install_status', 'installed');
            $this->markAllMigrationsFinished('2.1.17', [$migrationName]);

            $runner = new MigrationRunner();
            $runner->runPending('2.1.17', '2.1.17');

            self::assertSame('2.1.17', Setting::getConfigValue('schema_version'));
        } finally {
            @unlink($migrationPath);
        }
    }

    public function test_runner_statement_parser_skips_utf8_comment_lines(): void
    {
        $runner = new MigrationRunner();
        $method = new \ReflectionMethod($runner, 'splitStatements');
        $method->setAccessible(true);

        $statements = $method->invoke(
            $runner,
            "-- 升级脚本：确保中文注释不会被拆成 SQL\nINSERT INTO `setting` (`vkey`, `vvalue`) VALUES ('migration_utf8_test', '1');\n"
        );

        self::assertSame([
            "INSERT INTO `setting` (`vkey`, `vvalue`) VALUES ('migration_utf8_test', '1');",
        ], $statements);
    }

    public function test_runner_rejects_sql_files_outside_packaged_migration_directory(): void
    {
        $runner = new MigrationRunner();
        self::assertTrue(
            method_exists($runner, 'resolveTrustedMigrationPath'),
            'MigrationRunner must validate SQL file paths before execution.'
        );

        $path = tempnam(sys_get_temp_dir(), 'vpay-migration-');
        self::assertIsString($path);
        file_put_contents($path, 'SELECT 1;');

        $method = new \ReflectionMethod($runner, 'resolveTrustedMigrationPath');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Untrusted migration path');

        try {
            $method->invoke($runner, $path);
        } finally {
            @unlink($path);
        }
    }

    private function migrationPath(string $name): string
    {
        return app()->getRootPath() . 'database/migrations/' . $name . '.sql';
    }

    /**
     * @param list<string> $except
     */
    private function markAllMigrationsFinished(string $version, array $except = []): void
    {
        app()->make(MigrationLogService::class)->ensureTable();
        Db::execute('DELETE FROM `system_migration_log`');

        $scanner = new MigrationScanner();
        foreach ($scanner->upTo($version) as $migration) {
            $key = (string) $migration['migration_key'];
            if (in_array($key, $except, true)) {
                continue;
            }

            Db::name('system_migration_log')->insert([
                'migration_key' => $key,
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
