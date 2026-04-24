<?php
declare(strict_types=1);

namespace app\service\install;

class MigrationScanner
{
    /**
     * @return list<array{version: string, path: string, relative_path: string, migration_key: string}>
     */
    public function between(string $current, string $target): array
    {
        $root = app()->getRootPath() . 'database/migrations';
        if (!is_dir($root)) {
            return [];
        }

        $versions = array_values(array_filter(
            scandir($root) ?: [],
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($root . DIRECTORY_SEPARATOR . $entry)
        ));
        sort($versions, SORT_NATURAL);

        $files = [];
        foreach ($versions as $version) {
            if (version_compare($version, $current, '<=') || version_compare($version, $target, '>')) {
                continue;
            }

            foreach (glob($root . DIRECTORY_SEPARATOR . $version . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $path) {
                $fileName = basename($path);
                $files[] = [
                    'version' => $version,
                    'path' => $path,
                    'relative_path' => 'database/migrations/' . $version . '/' . $fileName,
                    'migration_key' => $version . '/' . $fileName,
                ];
            }
        }

        usort(
            $files,
            static fn (array $left, array $right): int => [$left['version'], $left['path']] <=> [$right['version'], $right['path']]
        );

        return $files;
    }
}
