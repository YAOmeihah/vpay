<?php
declare(strict_types=1);

namespace app\service\update;

use app\service\CacheService;
use app\service\install\MigrationRunner;
use RuntimeException;

final class UpdateApplyService
{
    /**
     * @var list<string>
     */
    private const MANAGED_DIRS = ['app', 'config', 'database', 'extend', 'public', 'route', 'vendor', 'view'];

    /**
     * @var list<string>
     */
    private const MANAGED_FILES = ['composer.json', 'composer.lock', 'LICENSE.txt', 'README-INSTALL.md', 'think', 'vmq.sql', 'release-manifest.json'];

    public function __construct(
        private readonly ?string $rootPath = null,
        private readonly ?UpdateStateStore $stateStore = null,
        private readonly mixed $migrationRunner = null
    ) {
    }

    public function apply(array $context): array
    {
        $fromVersion = (string) ($context['from_version'] ?? '');
        $targetVersion = (string) ($context['target_version'] ?? '');
        $packageRoot = (string) ($context['package_root'] ?? '');
        $backupPath = (string) ($context['backup_path'] ?? '');
        if ($fromVersion === '' || $targetVersion === '' || $packageRoot === '') {
            throw new RuntimeException('更新上下文不完整');
        }
        if (!is_dir($packageRoot)) {
            throw new RuntimeException('更新包目录不存在: ' . $packageRoot);
        }

        $store = $this->stateStore();
        $store->writeLock([
            'stage' => 'copy',
            'from_version' => $fromVersion,
            'target_version' => $targetVersion,
            'started_at' => time(),
        ]);

        try {
            $store->writeStatus(['stage' => 'copy', 'message' => '正在覆盖程序文件']);
            $this->copyPackage($packageRoot);

            $store->writeStatus(['stage' => 'migrate', 'message' => '正在执行数据库迁移']);
            $this->runMigrations($fromVersion, $targetVersion);

            CacheService::clearAll();

            $result = [
                'status' => 'updated',
                'from_version' => $fromVersion,
                'target_version' => $targetVersion,
                'backup_path' => $backupPath,
                'completed_at' => time(),
            ];
            $store->writeStatus(['stage' => 'complete', 'message' => '更新完成']);
            $store->writeSuccess($result);
            $store->clearLock();

            return $result;
        } catch (\Throwable $exception) {
            $stage = (string) (($store->status()['stage'] ?? 'apply'));
            $store->writeError([
                'stage' => $stage,
                'from_version' => $fromVersion,
                'target_version' => $targetVersion,
                'message' => $exception->getMessage(),
                'backup_path' => $backupPath,
            ]);
            $store->clearLock();

            throw $exception;
        }
    }

    private function copyPackage(string $packageRoot): void
    {
        foreach (self::MANAGED_DIRS as $dir) {
            $source = $packageRoot . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($source)) {
                $this->copyDirectory($source, $this->root() . $dir, $dir);
            }
        }

        foreach (self::MANAGED_FILES as $file) {
            $source = $packageRoot . DIRECTORY_SEPARATOR . $file;
            if (is_file($source)) {
                $this->copyFile($source, $this->root() . $file);
            }
        }
    }

    private function copyDirectory(string $source, string $target, string $relativeRoot): void
    {
        if ($this->shouldPreserve($relativeRoot)) {
            return;
        }
        if (!is_dir($target) && !mkdir($target, 0777, true)) {
            throw new RuntimeException('无法创建目录: ' . $target);
        }

        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $targetPath = $target . DIRECTORY_SEPARATOR . $item;
            $relativePath = $relativeRoot . '/' . $item;
            if ($this->shouldPreserve($relativePath)) {
                continue;
            }

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $targetPath, $relativePath);
                continue;
            }

            if (is_file($sourcePath)) {
                $this->copyFile($sourcePath, $targetPath);
            }
        }
    }

    private function copyFile(string $source, string $target): void
    {
        $this->assertInsideRoot($target);
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException('无法创建目录: ' . $dir);
        }

        $temporary = $target . '.update-tmp';
        if (!copy($source, $temporary)) {
            throw new RuntimeException('复制更新文件失败: ' . $target);
        }
        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('替换更新文件失败: ' . $target);
        }
    }

    private function runMigrations(string $fromVersion, string $targetVersion): void
    {
        if (is_callable($this->migrationRunner)) {
            ($this->migrationRunner)($fromVersion, $targetVersion);
            return;
        }

        app()->make(MigrationRunner::class)->runPending($fromVersion, $targetVersion);
    }

    private function shouldPreserve(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        return $relativePath === '.env'
            || $relativePath === 'runtime'
            || str_starts_with($relativePath, 'runtime/')
            || $relativePath === 'public/runtime'
            || str_starts_with($relativePath, 'public/runtime/')
            || $relativePath === 'runtime/update'
            || str_starts_with($relativePath, 'runtime/update/')
            || $relativePath === 'runtime/install'
            || str_starts_with($relativePath, 'runtime/install/');
    }

    private function assertInsideRoot(string $target): void
    {
        $root = $this->normalize($this->root());
        $dir = dirname($target);
        if (!is_dir($dir)) {
            $dir = dirname($dir);
        }
        $normalizedDir = $this->normalize($dir);
        if ($normalizedDir !== $root && !str_starts_with($normalizedDir, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('拒绝写入项目目录外的路径: ' . $target);
        }
    }

    private function normalize(string $path): string
    {
        $real = realpath($path);
        if ($real !== false) {
            return rtrim($real, DIRECTORY_SEPARATOR);
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private function root(): string
    {
        $root = $this->rootPath ?? app()->getRootPath();

        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function stateStore(): UpdateStateStore
    {
        return $this->stateStore ?? new UpdateStateStore($this->root());
    }
}
