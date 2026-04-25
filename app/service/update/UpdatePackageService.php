<?php
declare(strict_types=1);

namespace app\service\update;

use RuntimeException;
use ZipArchive;

final class UpdatePackageService
{
    private const DEFAULT_MAX_PACKAGE_BYTES = 104857600;
    private const MAX_SHA256_BYTES = 1048576;
    private const TRUSTED_RELEASE_HOST = 'github.com';
    private const TRUSTED_RELEASE_PATH_PREFIX = '/YAOmeihah/vpay/releases/download/';

    public function __construct(
        private readonly ?string $rootPath = null,
        private readonly int $maxPackageBytes = self::DEFAULT_MAX_PACKAGE_BYTES
    )
    {
    }

    public function download(array $assets, string $tagName): array
    {
        if (!preg_match('/^v\d+\.\d+\.\d+$/', $tagName)) {
            throw new RuntimeException('Release tag 格式不正确');
        }

        $zip = $assets['zip'] ?? null;
        $sha = $assets['sha256'] ?? null;
        if (!is_array($zip) || !is_array($sha)) {
            throw new RuntimeException('缺少更新包或 SHA256 校验文件');
        }
        $this->assertTrustedAsset($zip, $this->maxPackageBytes);
        $this->assertTrustedAsset($sha, self::MAX_SHA256_BYTES);

        $downloadDir = $this->updatePath() . DIRECTORY_SEPARATOR . 'downloads';
        $this->ensureDirectory($downloadDir);

        $zipPath = $downloadDir . DIRECTORY_SEPARATOR . 'vpay-' . $tagName . '.zip';
        $shaPath = $zipPath . '.sha256';
        $this->downloadFile((string) ($zip['download_url'] ?? ''), $zipPath, $this->maxPackageBytes);
        $this->downloadFile((string) ($sha['download_url'] ?? ''), $shaPath, self::MAX_SHA256_BYTES);

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

    private function downloadFile(string $url, string $target, int $maxBytes): void
    {
        if ($url === '') {
            throw new RuntimeException('下载地址不能为空');
        }

        $part = $target . '.part';
        try {
            $this->fetchToFile($url, $part, $maxBytes);
            if (!rename($part, $target)) {
                throw new RuntimeException('保存下载文件失败: ' . $target);
            }
        } catch (\Throwable $exception) {
            @unlink($part);
            throw $exception;
        }
    }

    private function fetchToFile(string $url, string $target, int $maxBytes): void
    {
        if (function_exists('curl_init')) {
            $targetHandle = fopen($target, 'wb');
            if ($targetHandle === false) {
                throw new RuntimeException('写入下载文件失败: ' . $target);
            }

            $curl = curl_init($url);
            if ($curl === false) {
                fclose($targetHandle);
                throw new RuntimeException('下载更新文件失败');
            }
            $written = 0;
            curl_setopt_array($curl, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => ['User-Agent: VPay-Updater'],
                CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use ($targetHandle, &$written, $maxBytes): int {
                    $written += strlen($chunk);
                    if ($written > $maxBytes) {
                        return 0;
                    }

                    $result = fwrite($targetHandle, $chunk);

                    return $result === false ? 0 : $result;
                },
            ]);
            $ok = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            fclose($targetHandle);

            if ($written > $maxBytes) {
                throw new RuntimeException('更新文件超过允许大小');
            }
            if ($ok !== true || $status >= 400 || !is_file($target) || filesize($target) <= 0) {
                throw new RuntimeException('下载更新文件失败: ' . ($error !== '' ? $error : 'HTTP ' . $status));
            }

            return;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'header' => "User-Agent: VPay-Updater\r\n",
            ],
        ]);
        $source = @fopen($url, 'rb', false, $context);
        if ($source === false) {
            throw new RuntimeException('下载更新文件失败');
        }

        $targetHandle = fopen($target, 'wb');
        if ($targetHandle === false) {
            fclose($source);
            throw new RuntimeException('写入下载文件失败: ' . $target);
        }

        $written = 0;
        try {
            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) {
                    throw new RuntimeException('下载更新文件失败');
                }
                if ($chunk === '') {
                    continue;
                }

                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    throw new RuntimeException('更新文件超过允许大小');
                }
                if (fwrite($targetHandle, $chunk) === false) {
                    throw new RuntimeException('写入下载文件失败: ' . $target);
                }
            }
        } finally {
            fclose($source);
            fclose($targetHandle);
        }

        if ($written <= 0) {
            throw new RuntimeException('下载更新文件失败');
        }
    }

    private function expectedSha256(string $sha256Path): string
    {
        $contents = trim((string) file_get_contents($sha256Path));
        if (!preg_match('/^[a-fA-F0-9]{64}/', $contents, $matches)) {
            throw new RuntimeException('SHA256 校验文件格式不正确');
        }

        return strtolower($matches[0]);
    }

    private function assertTrustedAsset(array $asset, int $maxBytes): void
    {
        $url = (string) ($asset['download_url'] ?? '');
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (
            $scheme !== 'https'
            || $host !== self::TRUSTED_RELEASE_HOST
            || !str_starts_with($path, self::TRUSTED_RELEASE_PATH_PREFIX)
        ) {
            throw new RuntimeException('下载地址必须指向 GitHub Release');
        }

        $size = (int) ($asset['size'] ?? 0);
        if ($size > $maxBytes) {
            throw new RuntimeException('更新包超过允许大小');
        }
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
