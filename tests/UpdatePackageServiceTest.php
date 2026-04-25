<?php
declare(strict_types=1);

namespace tests;

use app\service\update\UpdatePackageService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class UpdatePackageServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-update-package-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);

        parent::tearDown();
    }

    public function test_verifies_and_extracts_valid_release_package(): void
    {
        [$zipPath, $shaPath] = $this->createPackage('v2.1.2');
        $service = new UpdatePackageService($this->root);

        $result = $service->verifyAndExtract($zipPath, $shaPath, 'v2.1.2');

        self::assertSame('v2.1.2', $result['tag_name']);
        self::assertDirectoryExists($result['package_root']);
        self::assertFileExists($result['package_root'] . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php');
        self::assertFileExists($result['package_root'] . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.html');
    }

    public function test_rejects_checksum_mismatch(): void
    {
        [$zipPath, $shaPath] = $this->createPackage('v2.1.2');
        file_put_contents($shaPath, str_repeat('0', 64) . '  ' . basename($zipPath));
        $service = new UpdatePackageService($this->root);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHA256');

        $service->verifyAndExtract($zipPath, $shaPath, 'v2.1.2');
    }

    public function test_rejects_missing_manifest(): void
    {
        [$zipPath, $shaPath] = $this->createPackage('v2.1.2', [
            'vpay-v2.1.2/release-manifest.json' => null,
        ]);
        $service = new UpdatePackageService($this->root);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('release-manifest.json');

        $service->verifyAndExtract($zipPath, $shaPath, 'v2.1.2');
    }

    public function test_rejects_zip_slip_paths(): void
    {
        [$zipPath, $shaPath] = $this->createPackage('v2.1.2', [
            '../evil.php' => '<?php echo "bad";',
        ]);
        $service = new UpdatePackageService($this->root);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('非法路径');

        $service->verifyAndExtract($zipPath, $shaPath, 'v2.1.2');
    }

    public function test_rejects_non_github_release_download_urls(): void
    {
        $localZip = $this->root . DIRECTORY_SEPARATOR . 'local.zip';
        $localSha = $localZip . '.sha256';
        file_put_contents($localZip, 'zip');
        file_put_contents($localSha, hash_file('sha256', $localZip));

        $service = new UpdatePackageService($this->root);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GitHub Release');

        $service->download([
            'zip' => ['download_url' => 'file:///' . str_replace('\\', '/', $localZip), 'size' => 3],
            'sha256' => ['download_url' => 'file:///' . str_replace('\\', '/', $localSha), 'size' => 64],
        ], 'v2.1.2');
    }

    public function test_rejects_release_package_larger_than_download_limit(): void
    {
        $service = new UpdatePackageService($this->root, 5);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('超过允许大小');

        $service->download([
            'zip' => [
                'download_url' => 'https://github.com/YAOmeihah/vpay/releases/download/v2.1.2/vpay-v2.1.2.zip',
                'size' => 6,
            ],
            'sha256' => [
                'download_url' => 'https://github.com/YAOmeihah/vpay/releases/download/v2.1.2/vpay-v2.1.2.zip.sha256',
                'size' => 64,
            ],
        ], 'v2.1.2');
    }

    private function createPackage(string $tag, array $overrides = []): array
    {
        $zipPath = $this->root . DIRECTORY_SEPARATOR . 'vpay-' . $tag . '.zip';
        $shaPath = $zipPath . '.sha256';
        $appVersion = ltrim($tag, 'vV');
        $entries = [
            'vpay-' . $tag . '/release-manifest.json' => json_encode([
                'name' => 'vpay',
                'version' => $tag,
                'app_version' => $appVersion,
                'contains_vendor' => true,
                'contains_console_build' => true,
            ], JSON_UNESCAPED_SLASHES),
            'vpay-' . $tag . '/config/app.php' => "<?php return ['ver' => '{$appVersion}'];",
            'vpay-' . $tag . '/public/index.php' => '<?php',
            'vpay-' . $tag . '/public/index.html' => '<!doctype html>',
            'vpay-' . $tag . '/vendor/autoload.php' => '<?php',
            'vpay-' . $tag . '/database/migrations/.keep' => '',
        ];

        foreach ($overrides as $name => $contents) {
            if ($contents === null) {
                unset($entries[$name]);
            } else {
                $entries[$name] = $contents;
            }
        }

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, (string) $contents);
        }
        $zip->close();

        file_put_contents($shaPath, hash_file('sha256', $zipPath) . '  ' . basename($zipPath));

        return [$zipPath, $shaPath];
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
