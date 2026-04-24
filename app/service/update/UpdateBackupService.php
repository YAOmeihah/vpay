<?php
declare(strict_types=1);

namespace app\service\update;

use RuntimeException;
use ZipArchive;

final class UpdateBackupService
{
    /**
     * @var list<string>
     */
    private const MANAGED_DIRS = ['app', 'config', 'database', 'extend', 'public', 'route', 'vendor', 'view'];

    /**
     * @var list<string>
     */
    private const MANAGED_FILES = ['.env', 'composer.json', 'composer.lock', 'think', 'vmq.sql', 'release-manifest.json'];

    public function __construct(private readonly ?string $rootPath = null)
    {
    }

    public function backup(string $fromVersion, string $targetVersion): array
    {
        $backupDir = $this->root() . 'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true)) {
            throw new RuntimeException('无法创建备份目录: ' . $backupDir);
        }

        $fileName = 'v' . $targetVersion . '-from-v' . $fromVersion . '-' . date('Ymd-His') . '.zip';
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $fileName;
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建备份包: ' . $backupPath);
        }

        try {
            foreach (self::MANAGED_DIRS as $dir) {
                $path = $this->root() . $dir;
                if (is_dir($path)) {
                    $this->addDirectory($zip, $path, $dir);
                }
            }

            foreach (self::MANAGED_FILES as $file) {
                $path = $this->root() . $file;
                if (is_file($path)) {
                    $zip->addFile($path, $file);
                }
            }
        } finally {
            $zip->close();
        }

        return [
            'path' => $backupPath,
            'from_version' => $fromVersion,
            'target_version' => $targetVersion,
        ];
    }

    private function addDirectory(ZipArchive $zip, string $path, string $relativePath): void
    {
        if ($this->shouldExclude($relativePath)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $source = $path . DIRECTORY_SEPARATOR . $item;
            $relative = $relativePath . '/' . $item;
            if ($this->shouldExclude($relative)) {
                continue;
            }

            if (is_dir($source)) {
                $this->addDirectory($zip, $source, $relative);
                continue;
            }

            if (is_file($source)) {
                $zip->addFile($source, str_replace('\\', '/', $relative));
            }
        }
    }

    private function shouldExclude(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        return $relativePath === 'runtime'
            || str_starts_with($relativePath, 'runtime/')
            || $relativePath === 'public/runtime'
            || str_starts_with($relativePath, 'public/runtime/');
    }

    private function root(): string
    {
        $root = $this->rootPath ?? app()->getRootPath();

        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
