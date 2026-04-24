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
}
