<?php
declare(strict_types=1);

namespace app\service\update;

final class UpdatePreflightService
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
        private readonly ?UpdateStateStore $stateStore = null
    ) {
    }

    public function check(array $release = []): array
    {
        $checks = [];
        $root = $this->root();
        $store = $this->stateStore();

        $checks[] = $this->checkBool('更新目录可写', $this->ensureWritableDirectory($store->updatePath()), 'runtime/update/ 可创建并写入');
        $checks[] = $this->checkBool('项目根目录可写', is_writable($root), '项目根目录可写');

        foreach (self::MANAGED_DIRS as $dir) {
            $path = $root . $dir;
            $checks[] = $this->checkBool(
                $dir . ' 可写',
                $this->managedDirectoryWritable($root, $dir),
                $dir . ' 目录可写或可创建'
            );
        }
        $managedWrite = $this->managedPathsWritable($root);
        $checks[] = $this->checkBool(
            '程序文件可写',
            $managedWrite['ok'],
            $managedWrite['message']
        );

        $checks[] = $this->checkBool('.env 可读', is_readable($root . '.env'), '.env 可读且不会被覆盖');
        $checks[] = $this->checkBool('ZipArchive 可用', class_exists(\ZipArchive::class), 'PHP ZipArchive 扩展可用');
        $checks[] = $this->checkBool('HTTPS 下载能力可用', function_exists('curl_init') || (bool) ini_get('allow_url_fopen'), 'curl 或 allow_url_fopen 可用');
        $checks[] = $this->checkBool('没有更新锁', !$store->hasLock(), '当前已有更新任务正在执行');
        $checks[] = $this->checkBool('没有安装锁', !is_file($root . 'runtime' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'lock.json'), '安装或升级正在执行');
        $checks[] = $this->checkBool('没有安装恢复错误', !is_file($root . 'runtime' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'last-error.json'), '安装或升级失败状态需要先处理');

        $zipSize = $this->releaseZipSize($release);
        $free = @disk_free_space($root);
        $checks[] = $this->checkBool('磁盘空间充足', $free === false || $zipSize <= 0 || $free >= ($zipSize * 3), '磁盘剩余空间不足');

        $ok = count(array_filter($checks, static fn (array $check): bool => $check['ok'] !== true)) === 0;

        return [
            'ok' => $ok,
            'can_update' => $ok,
            'checks' => $checks,
        ];
    }

    private function checkBool(string $label, bool $ok, string $message): array
    {
        return ['label' => $label, 'ok' => $ok, 'message' => $ok ? '通过' : $message];
    }

    private function ensureWritableDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }

        return is_dir($path) && is_writable($path);
    }

    private function managedDirectoryWritable(string $root, string $dir): bool
    {
        $path = $root . $dir;
        if (is_dir($path)) {
            return $this->canWriteTemporaryFile($path);
        }

        return $this->canWriteTemporaryFile($root);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function managedPathsWritable(string $root): array
    {
        foreach (self::MANAGED_DIRS as $dir) {
            $path = $root . $dir;
            if (!is_dir($path)) {
                continue;
            }

            $failure = $this->firstUnwritablePath($path);
            if ($failure !== '') {
                return [
                    'ok' => false,
                    'message' => '程序文件存在不可写路径: ' . $this->relativePath($failure, $root),
                ];
            }
        }

        foreach (self::MANAGED_FILES as $file) {
            $path = $root . $file;
            if (is_file($path) && (!is_writable($path) || !$this->canWriteTemporaryFile(dirname($path)))) {
                return [
                    'ok' => false,
                    'message' => '程序文件存在不可写路径: ' . $file,
                ];
            }
        }

        return ['ok' => true, 'message' => '程序文件可写'];
    }

    private function firstUnwritablePath(string $path): string
    {
        if (!$this->canWriteTemporaryFile($path)) {
            return $path;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $failure = $this->firstUnwritablePath($child);
                if ($failure !== '') {
                    return $failure;
                }
                continue;
            }

            if (is_file($child) && (!is_writable($child) || !$this->canWriteTemporaryFile(dirname($child)))) {
                return $child;
            }
        }

        return '';
    }

    private function canWriteTemporaryFile(string $dir): bool
    {
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        $path = $dir . DIRECTORY_SEPARATOR . '.vpay-update-preflight-' . bin2hex(random_bytes(4)) . '.tmp';
        $written = @file_put_contents($path, 'ok');
        if ($written === false) {
            return false;
        }

        @unlink($path);

        return true;
    }

    private function relativePath(string $path, string $root): string
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    private function releaseZipSize(array $release): int
    {
        $direct = (int) ($release['zip_size'] ?? 0);
        if ($direct > 0) {
            return $direct;
        }

        return (int) ($release['assets']['zip']['size'] ?? 0);
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
