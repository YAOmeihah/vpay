<?php
declare(strict_types=1);

namespace app\service\update;

final class UpdatePreflightService
{
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

        foreach (['app', 'config', 'database', 'route', 'vendor', 'view', 'public'] as $dir) {
            $path = $root . $dir;
            $checks[] = $this->checkBool($dir . ' 可写', is_dir($path) && is_writable($path), $dir . ' 目录可写');
        }

        $checks[] = $this->checkBool('.env 可读', is_readable($root . '.env'), '.env 可读且不会被覆盖');
        $checks[] = $this->checkBool('ZipArchive 可用', class_exists(\ZipArchive::class), 'PHP ZipArchive 扩展可用');
        $checks[] = $this->checkBool('HTTPS 下载能力可用', function_exists('curl_init') || (bool) ini_get('allow_url_fopen'), 'curl 或 allow_url_fopen 可用');
        $checks[] = $this->checkBool('没有更新锁', !$store->hasLock(), '当前已有更新任务正在执行');
        $checks[] = $this->checkBool('没有安装锁', !is_file($root . 'runtime' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'lock.json'), '安装或升级正在执行');
        $checks[] = $this->checkBool('没有安装恢复错误', !is_file($root . 'runtime' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'last-error.json'), '安装或升级失败状态需要先处理');

        $zipSize = (int) ($release['zip_size'] ?? 0);
        $free = @disk_free_space($root);
        $checks[] = $this->checkBool('磁盘空间充足', $free === false || $zipSize <= 0 || $free >= ($zipSize * 3), '磁盘剩余空间不足');

        return [
            'ok' => count(array_filter($checks, static fn (array $check): bool => $check['ok'] !== true)) === 0,
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
