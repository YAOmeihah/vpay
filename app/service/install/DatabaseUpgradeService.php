<?php
declare(strict_types=1);

namespace app\service\install;

use app\model\Setting;

class DatabaseUpgradeService
{
    private const LEGACY_BASELINE_VERSION = '2.0.0';

    /**
     * @return array{current_version: string, target_version: string, migrations: list<array{relative_path: string}>}
     */
    public function context(?string $currentVersion = null, ?string $targetVersion = null): array
    {
        $current = (string) ($currentVersion ?? Setting::getConfigValue('schema_version'));
        if ($current === '') {
            $current = self::LEGACY_BASELINE_VERSION;
        }

        $target = (string) ($targetVersion ?? config('app.ver'));

        return [
            'current_version' => $current,
            'target_version' => $target,
            'migrations' => array_map(
                static fn (array $item): array => ['relative_path' => (string) $item['relative_path']],
                $this->pendingMigrations($target)
            ),
        ];
    }

    /**
     * @return list<array{version: string, path: string, relative_path: string, migration_key: string}>
     */
    public function pendingMigrations(string $targetVersion): array
    {
        return app()->make(MigrationLogService::class)->pending(
            app()->make(MigrationScanner::class)->upTo($targetVersion)
        );
    }

    public function run(string $currentVersion, string $targetVersion): void
    {
        app()->make(MigrationRunner::class)->runPending($currentVersion, $targetVersion);
    }
}

