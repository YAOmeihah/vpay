<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\install\MigrationRunner;
use app\service\install\MigrationScanner;
use think\facade\Db;

final class MigrationRunnerTest extends TestCase
{
    public function test_scanner_returns_ordered_migrations_between_versions(): void
    {
        $scanner = new MigrationScanner();
        $files = $scanner->between('2.0.0', '2.1.0');

        self::assertSame([
            'database/migrations/2.1.0/001-create-system-migration-log.sql',
            'database/migrations/2.1.0/002-backfill-install-state.sql',
            'database/migrations/2.1.0/003-ensure-notify-ssl-verify.sql',
        ], array_map(static fn (array $item): string => $item['relative_path'], $files));
    }

    public function test_current_release_includes_log_bootstrap_migration_for_legacy_2_1_13_databases(): void
    {
        $scanner = new MigrationScanner();
        $files = $scanner->between('2.1.13', (string) config('app.ver'));

        self::assertContains(
            'database/migrations/2.1.14/001-ensure-system-migration-log.sql',
            array_map(static fn (array $item): string => $item['relative_path'], $files)
        );
    }

    public function test_log_bootstrap_migration_creates_log_table_and_backfills_existing_migrations(): void
    {
        Db::execute('DROP TABLE IF EXISTS `system_migration_log`');

        try {
            $path = app()->getRootPath() . 'database/migrations/2.1.14/001-ensure-system-migration-log.sql';
            $this->executeSqlFile($path);
            $this->executeSqlFile($path);

            $expectedKeys = [
                '2.1.0/001-create-system-migration-log.sql',
                '2.1.0/002-backfill-install-state.sql',
                '2.1.0/003-ensure-notify-ssl-verify.sql',
                '2.1.11/001-create-terminal-allocation-cursor.sql',
                '2.1.13/001-add-pay-order-sign-type.sql',
            ];
            $rows = Db::name('system_migration_log')
                ->whereIn('migration_key', $expectedKeys)
                ->select()
                ->toArray();
            $statuses = array_column($rows, 'status', 'migration_key');

            foreach ($expectedKeys as $key) {
                self::assertSame('finished', $statuses[$key] ?? null);
            }
        } finally {
            Db::execute('DROP TABLE IF EXISTS `system_migration_log`');
        }
    }

    public function test_runner_executes_pending_migrations_and_updates_schema_version(): void
    {
        Setting::setConfigValue('schema_version', '2.0.0');
        Setting::setConfigValue('app_version', '2.0.0');
        Setting::setConfigValue('install_status', 'installed');

        $runner = new MigrationRunner();
        $runner->runPending('2.0.0', '2.1.0');

        self::assertSame('2.1.0', Setting::getConfigValue('schema_version'));
        self::assertSame('2.1.0', Setting::getConfigValue('app_version'));
        self::assertNotEmpty(Db::name('system_migration_log')->select()->toArray());
    }

    public function test_runner_updates_versions_when_patch_release_has_no_sql_migrations(): void
    {
        Setting::setConfigValue('schema_version', '2.1.0');
        Setting::setConfigValue('app_version', '2.1.0');
        Setting::setConfigValue('install_status', 'installed');

        $runner = new MigrationRunner();
        $runner->runPending('2.1.0', '2.1.1');

        self::assertSame('2.1.1', Setting::getConfigValue('schema_version'));
        self::assertSame('2.1.1', Setting::getConfigValue('app_version'));
    }

    public function test_runner_skips_add_column_migration_when_column_already_exists(): void
    {
        Setting::setConfigValue('schema_version', '2.1.12');
        Setting::setConfigValue('app_version', '2.1.12');
        Setting::setConfigValue('install_status', 'installed');

        $runner = new MigrationRunner();
        $runner->runPending('2.1.12', '2.1.13');

        self::assertSame('2.1.13', Setting::getConfigValue('schema_version'));
        self::assertSame('2.1.13', Setting::getConfigValue('app_version'));
    }

    public function test_runner_executes_unfinished_migration_when_schema_version_matches_target(): void
    {
        $migrationPath = app()->getRootPath() . 'database/migrations/9.9.9/001-log-aware-test.sql';
        $migrationDir = dirname($migrationPath);

        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0777, true);
        }
        file_put_contents(
            $migrationPath,
            "INSERT INTO `setting` (`vkey`, `vvalue`) VALUES ('log_aware_migration', 'ran')"
            . " ON DUPLICATE KEY UPDATE `vvalue` = VALUES(`vvalue`);\n"
        );

        try {
            Setting::setConfigValue('schema_version', '9.9.9');
            Setting::setConfigValue('app_version', '9.9.9');
            Setting::setConfigValue('install_status', 'installed');

            $runner = new MigrationRunner();
            $runner->runPending('9.9.9', '9.9.9');

            self::assertSame('ran', Setting::getConfigValue('log_aware_migration'));
        } finally {
            @unlink($migrationPath);
            @rmdir($migrationDir);
        }
    }

    public function test_runner_skips_finished_migration_log_entries(): void
    {
        $migrationPath = app()->getRootPath() . 'database/migrations/9.9.9/002-log-aware-skip-test.sql';
        $migrationDir = dirname($migrationPath);

        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0777, true);
        }
        file_put_contents(
            $migrationPath,
            "UPDATE `setting` SET `vvalue` = 'changed' WHERE `vkey` = 'log_aware_skip';\n"
        );

        try {
            Setting::setConfigValue('schema_version', '9.9.8');
            Setting::setConfigValue('app_version', '9.9.8');
            Setting::setConfigValue('install_status', 'installed');
            Setting::setConfigValue('log_aware_skip', 'keep');

            Db::execute((string) file_get_contents(app()->getRootPath() . 'database/migrations/2.1.0/001-create-system-migration-log.sql'));
            Db::name('system_migration_log')->insert([
                'migration_key' => '9.9.9/002-log-aware-skip-test.sql',
                'from_version' => '9.9.8',
                'to_version' => '9.9.9',
                'status' => 'finished',
                'started_at' => time(),
                'finished_at' => time(),
                'error_message' => '',
                'checksum' => sha1((string) file_get_contents($migrationPath)),
            ]);

            $runner = new MigrationRunner();
            $runner->runPending('9.9.8', '9.9.9');

            self::assertSame('keep', Setting::getConfigValue('log_aware_skip'));
        } finally {
            @unlink($migrationPath);
            @rmdir($migrationDir);
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

    private function executeSqlFile(string $path): void
    {
        self::assertFileExists($path);

        $runner = new MigrationRunner();
        $method = new \ReflectionMethod($runner, 'splitStatements');
        $method->setAccessible(true);

        foreach ($method->invoke($runner, (string) file_get_contents($path)) as $statement) {
            $trimmed = trim((string) $statement);
            if ($trimmed !== '') {
                Db::execute($trimmed);
            }
        }
    }
}
