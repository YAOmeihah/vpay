<?php
declare(strict_types=1);

namespace VPay\Build;

use RuntimeException;

final class ReleasePackageBuilder
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function stage(string $version, string $outputRoot): string
    {
        $version = $this->normalizeVersion($version);
        $root = $this->normalizePath($this->rootPath);
        $outputRoot = $this->normalizePath($outputRoot);
        $packageDir = $outputRoot . DIRECTORY_SEPARATOR . 'vpay-' . $version;

        $this->assertRequiredBuildArtifacts($root);
        $appVersion = $this->appVersion($root);

        if (!is_dir($outputRoot) && !mkdir($outputRoot, 0777, true)) {
            throw new RuntimeException('Unable to create release output directory: ' . $outputRoot);
        }

        if (is_dir($packageDir)) {
            $this->removeTree($packageDir, $outputRoot);
        }

        if (!mkdir($packageDir, 0777, true)) {
            throw new RuntimeException('Unable to create release package directory: ' . $packageDir);
        }

        foreach (['app', 'config', 'database', 'extend', 'public', 'route', 'vendor', 'view'] as $dir) {
            $source = $root . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($source)) {
                $this->copyDirectory($source, $packageDir . DIRECTORY_SEPARATOR . $dir, $dir);
            }
        }

        foreach ([
            '.example.env',
            'composer.json',
            'composer.lock',
            'LICENSE.txt',
            'README-INSTALL.md',
            'think',
            'vmq.sql',
        ] as $file) {
            $source = $root . DIRECTORY_SEPARATOR . $file;
            if (is_file($source)) {
                $this->copyFile($source, $packageDir . DIRECTORY_SEPARATOR . $file);
            }
        }

        $runtimeInstall = $packageDir . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'install';
        if (!is_dir($runtimeInstall) && !mkdir($runtimeInstall, 0777, true)) {
            throw new RuntimeException('Unable to create runtime install directory: ' . $runtimeInstall);
        }
        file_put_contents($runtimeInstall . DIRECTORY_SEPARATOR . '.keep', '');

        file_put_contents(
            $packageDir . DIRECTORY_SEPARATOR . 'release-manifest.json',
            json_encode([
                'name' => 'vpay',
                'version' => $version,
                'generated_at' => gmdate('c'),
                'contains_vendor' => true,
                'contains_console_build' => true,
                'app_version' => $appVersion,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'
        );

        return $packageDir;
    }

    private function assertRequiredBuildArtifacts(string $root): void
    {
        foreach ([
            'vendor/autoload.php',
            'public/console/index.html',
        ] as $required) {
            if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $required))) {
                throw new RuntimeException('Missing required release artifact: ' . $required);
            }
        }
    }

    private function copyDirectory(string $source, string $target, string $relativeRoot): void
    {
        if (!is_dir($target) && !mkdir($target, 0777, true)) {
            throw new RuntimeException('Unable to create directory: ' . $target);
        }

        $items = scandir($source) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $targetPath = $target . DIRECTORY_SEPARATOR . $item;
            $relativePath = $relativeRoot . '/' . $item;

            if ($this->shouldExclude($relativePath)) {
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
        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
            throw new RuntimeException('Unable to create directory: ' . $targetDir);
        }

        if (!copy($source, $target)) {
            throw new RuntimeException('Unable to copy file: ' . $source);
        }
    }

    private function shouldExclude(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        return $relativePath === 'public/runtime'
            || str_starts_with($relativePath, 'public/runtime/')
            || $relativePath === 'runtime/install/enable.flag';
    }

    private function removeTree(string $path, string $safeRoot): void
    {
        $safeRoot = $this->normalizePath($safeRoot);
        $path = $this->normalizePath($path);

        if ($path === $safeRoot || !str_starts_with($path, $safeRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Refusing to remove path outside release output: ' . $path);
        }

        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $item, $safeRoot);
        }

        @rmdir($path);
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '' || str_contains($version, '/') || str_contains($version, '\\')) {
            throw new RuntimeException('Invalid release version: ' . $version);
        }

        return $version;
    }

    private function appVersion(string $root): string
    {
        $configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        if (!is_file($configPath)) {
            return '';
        }

        $config = require $configPath;
        if (!is_array($config)) {
            return '';
        }

        return (string) ($config['ver'] ?? '');
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);
        if ($realPath !== false) {
            return rtrim($realPath, DIRECTORY_SEPARATOR);
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
    }
}
