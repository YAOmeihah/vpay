<?php
declare(strict_types=1);

namespace app\service\install;

use app\model\Setting;
use think\facade\Db;

class MigrationRunner
{
    public function runPending(string $current, string $target): void
    {
        $scanner = new MigrationScanner();
        $logger = new MigrationLogService();
        $migrations = $scanner->between($current, $target);

        if ($migrations === [] && version_compare($current, $target, '<')) {
            Setting::setConfigValue('schema_version', $target);
            Setting::setConfigValue('app_version', $target);
            return;
        }

        foreach ($migrations as $migration) {
            $fromVersion = $current;
            $logger->started($migration, $fromVersion);

            try {
                foreach ($this->splitStatements((string) file_get_contents((string) $migration['path'])) as $statement) {
                    $trimmed = trim($statement);
                    if ($trimmed === '') {
                        continue;
                    }

                    Db::execute($trimmed);
                }
            } catch (\Throwable $exception) {
                $logger->failed($migration, $fromVersion, $exception->getMessage());
                throw $exception;
            }

            $logger->finished($migration, $fromVersion);
            $current = (string) $migration['version'];
            Setting::setConfigValue('schema_version', $current);
            Setting::setConfigValue('app_version', $target);
        }
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $lines = preg_split('/\R/u', $sql) ?: [];
        $statements = [];
        $buffer = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (
                $trimmed === ''
                || str_starts_with($trimmed, '--')
                || str_starts_with($trimmed, '#')
                || (str_starts_with($trimmed, '/*') && !str_starts_with($trimmed, '/*!'))
            ) {
                continue;
            }

            $buffer .= $line . PHP_EOL;

            if (str_ends_with(rtrim($line), ';')) {
                $statements[] = trim($buffer);
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }
}
