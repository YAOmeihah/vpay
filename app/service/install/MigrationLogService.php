<?php
declare(strict_types=1);

namespace app\service\install;

use think\facade\Db;

class MigrationLogService
{
    public function started(array $migration, string $fromVersion): void
    {
        if (!$this->tableAvailable()) {
            return;
        }

        $this->upsert($migration, $fromVersion, 'started', '', time(), 0);
    }

    public function finished(array $migration, string $fromVersion): void
    {
        if (!$this->tableAvailable()) {
            return;
        }

        $startedAt = $this->currentStartedAt((string) $migration['migration_key']);
        $this->upsert($migration, $fromVersion, 'finished', '', $startedAt, time());
    }

    public function failed(array $migration, string $fromVersion, string $message): void
    {
        if (!$this->tableAvailable()) {
            return;
        }

        $startedAt = $this->currentStartedAt((string) $migration['migration_key']);
        $this->upsert($migration, $fromVersion, 'failed', $message, $startedAt, time());
    }

    private function upsert(
        array $migration,
        string $fromVersion,
        string $status,
        string $errorMessage,
        int $startedAt,
        int $finishedAt
    ): void {
        $data = [
            'migration_key' => (string) $migration['migration_key'],
            'from_version' => $fromVersion,
            'to_version' => (string) $migration['version'],
            'status' => $status,
            'started_at' => $startedAt > 0 ? $startedAt : time(),
            'finished_at' => $finishedAt,
            'error_message' => $errorMessage,
            'checksum' => sha1((string) file_get_contents((string) $migration['path'])),
        ];

        $exists = Db::name('system_migration_log')
            ->where('migration_key', $data['migration_key'])
            ->find();

        if ($exists === null) {
            Db::name('system_migration_log')->insert($data);
            return;
        }

        Db::name('system_migration_log')
            ->where('migration_key', $data['migration_key'])
            ->update($data);
    }

    private function currentStartedAt(string $migrationKey): int
    {
        $row = Db::name('system_migration_log')
            ->where('migration_key', $migrationKey)
            ->find();

        return (int) ($row['started_at'] ?? time());
    }

    private function tableAvailable(): bool
    {
        try {
            return Db::query("SHOW TABLES LIKE 'system_migration_log'") !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}
