<?php
declare(strict_types=1);

namespace app\service\install;

use think\facade\Db;

class MigrationLogService
{
    public function ensureTable(): void
    {
        Db::execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `system_migration_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_key` varchar(255) NOT NULL,
  `from_version` varchar(32) NOT NULL DEFAULT '',
  `to_version` varchar(32) NOT NULL DEFAULT '',
  `status` varchar(32) NOT NULL DEFAULT 'started',
  `started_at` bigint(20) NOT NULL DEFAULT 0,
  `finished_at` bigint(20) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `checksum` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_migration_key` (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
    }

    /**
     * @param list<array{migration_key: string}> $migrations
     * @return list<array{migration_key: string}>
     */
    public function pending(array $migrations): array
    {
        if ($migrations === [] || !$this->tableAvailable()) {
            return $migrations;
        }

        $finished = Db::name('system_migration_log')
            ->whereIn('migration_key', array_map(static fn (array $migration): string => (string) $migration['migration_key'], $migrations))
            ->where('status', 'finished')
            ->column('migration_key');

        $finished = array_flip(array_map('strval', $finished));

        return array_values(array_filter(
            $migrations,
            static fn (array $migration): bool => !isset($finished[(string) $migration['migration_key']])
        ));
    }

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
            Db::name('system_migration_log')->limit(1)->value('migration_key');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
