<?php
declare(strict_types=1);

namespace app\service\install;

class MigrationScanner
{
    private const MIGRATION_FILE_PATTERN = '/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.sql$/i';

    /**
     * @return list<array{version: string, path: string, relative_path: string, migration_key: string}>
     */
    public function between(string $current, string $target): array
    {
        return $this->scan($target);
    }

    /**
     * @return list<array{version: string, path: string, relative_path: string, migration_key: string}>
     */
    public function upTo(string $target): array
    {
        return $this->scan($target);
    }

    /**
     * @return list<array{version: string, path: string, relative_path: string, migration_key: string}>
     */
    private function scan(string $target): array
    {
        $root = app()->getRootPath() . 'database/migrations';
        if (!is_dir($root)) {
            return [];
        }

        $paths = glob($root . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($paths, SORT_STRING);

        $files = [];
        foreach ($paths as $path) {
            $fileName = basename($path);
            if (!preg_match(self::MIGRATION_FILE_PATTERN, $fileName)) {
                continue;
            }

            $files[] = [
                'version' => $target,
                'path' => $path,
                'relative_path' => 'database/migrations/' . $fileName,
                'migration_key' => pathinfo($fileName, PATHINFO_FILENAME),
            ];
        }

        return $files;
    }
}
