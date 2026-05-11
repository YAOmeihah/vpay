<?php
declare(strict_types=1);

namespace tests;

use app\service\install\DatabaseUpgradeService;
use app\service\install\MigrationRunner;

final class DatabaseUpgradeServiceTest extends TestCase
{
    public function test_context_uses_legacy_baseline_and_lists_pending_migrations(): void
    {
        $service = new DatabaseUpgradeService();

        $context = $service->context('', '2.1.16');

        self::assertSame('2.0.0', $context['current_version']);
        self::assertSame('2.1.16', $context['target_version']);
        self::assertSame([
            ['relative_path' => 'database/migrations/2026_05_11_090000_ensure_payment_success_notification_settings.sql'],
        ], $context['migrations']);
    }

    public function test_run_delegates_to_configured_migration_runner(): void
    {
        $runner = new class extends MigrationRunner {
            public string $fromVersion = '';
            public string $targetVersion = '';

            public function runPending(string $current, string $target): void
            {
                $this->fromVersion = $current;
                $this->targetVersion = $target;
            }
        };
        $this->app->instance(MigrationRunner::class, $runner);

        $service = new DatabaseUpgradeService();
        $service->run('2.1.0', '2.1.14');

        self::assertSame('2.1.0', $runner->fromVersion);
        self::assertSame('2.1.14', $runner->targetVersion);
    }
}
