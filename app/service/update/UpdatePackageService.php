<?php
declare(strict_types=1);

namespace app\service\update;

use RuntimeException;
use ZipArchive;

final class UpdatePackageService
{
    public function __construct(private readonly ?string $rootPath = null)
    {
    }

    public function download(array $assets, string $tagName): array
    {
        $zip = $assets['zip'] ?? null;
        $sha = $assets['sha256'] ?? null;
        if (!is_array($zip) || !is_array($sha)) {
            throw new RuntimeException('缺少更新包或 SHA256 校验文件');
        }

        $downloadDir = $this->updatePath() . DIRECTORY_SEPARATOR . 'downloads';
        $this->ensureDirectory($downloadDir);

        $zipPath = $downloadDir . DIRECTORY_SEPARATOR . 'vpay-' . $tagName . '.zip';
        $shaPath = $zipPath . '.sha256';
        $this->downloadFile((string) ($zip['download_url'] ?? ''), $zipPath);
        $this->downloadFile((string) ($sha['download_url'] ?? ''), $shaPath);

        return $this->verifyAndExtract($zipPath, $shaPath, $tagName);
    }

    public function verifyAndExtract(string $zipPath, string $sha256Path, string $tagName): array
    {
        if (!is_file($zipPath)) {
            throw new RuntimeException('更新包不存在: ' . $zipPath);
        }
        if (!is_file($sha256Path)) {
            throw new RuntimeException('SHA256 校验文件不存在: ' . $sha256Path);
        }

        $expected = $this->expectedSha256($sha256Path);
        $actual = hash_file('sha256', $zipPath);
        if (!hash_equals($expected, $actual)) {
            throw new RuntimeException('SHA256 校验失败');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('更新包无法打开');
        }

        try {
            $this->assertSafeZip($zip);
            $extractRoot = $this->updatePath() . DIRECTORY_SEPARATOR . 'extracted' . DIRECTORY_SEPARATOR . $tagName;
            $this->removeTree($extractRoot);
            $this->ensureDirectory($extractRoot);

            if (!$zip->extractTo($extractRoot)) {
                throw new RuntimeException('更新包解压失败');
            }
        } finally {
            $zip->close();
        }

        $packageRoot = $extractRoot . DIRECTORY_SEPARATOR . 'vpay-' . $tagName;
        $this->assertPackage($packageRoot, $tagName);

        return [
            'tag_name' => $tagName,
            'zip_path' => $zipPath,
            'sha256_path' => $sha256Path,
            'package_root' => $packageRoot,
        ];
    }

    private function downloadFile(string $url, string $target): void
    {
        if ($url === '') {
            throw new RuntimeException('下载地址不能为空');
        }

        $part = $target . '.part';
        $body = $this->fetch($url);
        if (file_put_contents($part, $body) === false) {
            throw new RuntimeException('写入下载文件失败: ' . $target);
        }
        if (!rename($part, $target)) {
            @unlink($part);
            throw new RuntimeException('保存下载文件失败: ' . $target);
        }
    }

    private function fetch(string $url): string
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => ['User-Agent: VPay-Updater'],
            ]);
            $body = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if (!is_string($body) || $body === '' || $status >= 400) {
                throw new RuntimeException('下载更新文件失败: ' . ($error !== '' ? $error : 'HTTP ' . $status));
            }

            return $body;
        }

        $body = @file_get_contents($url);
        if (!is_string($body) || $body === '') {
            throw new RuntimeException('下载更新文件失败');
        }

        return $body;
    }

    private function expectedSha256(string $sha256Path): string
    {
        $contents = trim((string) file_get_contents($sha256Path));
        if (!preg_match('/^[a-fA-F0-9]{64}/', $contents, $matches)) {
            throw new RuntimeException('SHA256 校验文件格式不正确');
        }

        return strtolower($matches[0]);
    }

    private function assertSafeZip(ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $normalized = str_replace('\\', '/', $name);
            if (
                $normalized === ''
                || str_starts_with($normalized, '/')
                || preg_match('/^[A-Za-z]:\//', $normalized)
                || str_contains($normalized, '../')
                || str_contains($normalized, '..\\')
                || $normalized === '..'
            ) {
                throw new RuntimeException('更新包包含非法路径: ' . $name);
            }
        }
    }

    private function assertPackage(string $packageRoot, string $tagName): void
    {
        $required = [
            'release-manifest.json',
            'config/app.php',
            'public/index.php',
            'public/index.html',
            'vendor/autoload.php',
            'database/migrations',
        ];
        foreach ($required as $relativePath) {
            $path = $packageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (!file_exists($path)) {
                throw new RuntimeException('更新包缺少必要文件: ' . $relativePath);
            }
        }

        $manifest = json_decode((string) file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'release-manifest.json'), true);
        if (!is_array($manifest)) {
            throw new RuntimeException('release-manifest.json 不是有效 JSON');
        }
        if (($manifest['version'] ?? '') !== $tagName) {
            throw new RuntimeException('release-manifest.json 版本不匹配');
        }
        if (($manifest['app_version'] ?? '') !== ltrim($tagName, 'vV')) {
            throw new RuntimeException('release-manifest.json 应用版本不匹配');
        }
    }

    private function updatePath(): string
    {
        return $this->root() . 'runtime' . DIRECTORY_SEPARATOR . 'update';
    }

    private function root(): string
    {
        $root = $this->rootPath ?? app()->getRootPath();

        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true)) {
            throw new RuntimeException('无法创建目录: ' . $path);
        }
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                $this->removeTree($path . DIRECTORY_SEPARATOR . $item);
            }
        }

        @rmdir($path);
    }
}
