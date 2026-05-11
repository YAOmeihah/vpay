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
        $logger->ensureTable();
        $migrations = $logger->pending($scanner->upTo($target));

        if ($migrations === [] && version_compare($current, $target, '<')) {
            Setting::setConfigValue('schema_version', $target);
            Setting::setConfigValue('app_version', $target);
            return;
        }

        foreach ($migrations as $migration) {
            $fromVersion = $current;
            $logger->started($migration, $fromVersion);

            try {
                $path = $this->resolveTrustedMigrationPath((string) $migration['path']);
                foreach ($this->splitStatements((string) file_get_contents($path)) as $statement) {
                    $trimmed = trim($statement);
                    if ($trimmed === '') {
                        continue;
                    }

                    if ($this->shouldSkipExistingColumnAdd($trimmed)) {
                        continue;
                    }

                    Db::execute($trimmed);
                }
            } catch (\Throwable $exception) {
                $logger->failed($migration, $fromVersion, $exception->getMessage());
                throw $exception;
            }

            $logger->finished($migration, $fromVersion);
            if (version_compare((string) $migration['version'], $current, '>')) {
                $current = (string) $migration['version'];
            }
            Setting::setConfigValue('schema_version', $current);
            Setting::setConfigValue('app_version', $target);
        }

        if (version_compare($current, $target, '<')) {
            Setting::setConfigValue('schema_version', $target);
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

    private function shouldSkipExistingColumnAdd(string $statement): bool
    {
        if (!preg_match(
            '/\AALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+ADD\s+(?:COLUMN\s+)?`?([a-z0-9_]+)`?\b/is',
            $statement,
            $matches
        )) {
            return false;
        }

        return $this->columnExists((string) $matches[1], (string) $matches[2]);
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $escapedColumn = str_replace(["\\", "'"], ["\\\\", "\\'"], $column);

            return Db::query("SHOW COLUMNS FROM {$quotedTable} LIKE '{$escapedColumn}'") !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveTrustedMigrationPath(string $path): string
    {
        $root = realpath(app()->getRootPath() . 'database' . DIRECTORY_SEPARATOR . 'migrations');
        $realPath = realpath($path);

        if ($root === false || $realPath === false) {
            throw new \RuntimeException('Untrusted migration path: ' . $path);
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $realPath);

        if (!str_starts_with($normalizedPath, $normalizedRoot)) {
            throw new \RuntimeException('Untrusted migration path: ' . $path);
        }

        $relativePath = substr($normalizedPath, strlen($normalizedRoot));
        if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.sql$/i', $relativePath)) {
            throw new \RuntimeException('Untrusted migration path: ' . $path);
        }

        return $realPath;
    }
}
