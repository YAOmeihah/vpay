<?php
declare(strict_types=1);

namespace tests;

use app\service\update\UpdateBackupService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class UpdateBackupServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-update-backup-' . bin2hex(random_bytes(4));
        $this->writeFile('app/AppService.php', '<?php');
        $this->writeFile('config/app.php', '<?php return [];');
        $this->writeFile('public/index.php', '<?php');
        $this->writeFile('public/index.html', '<!doctype html>');
        $this->writeFile('route/app.php', '<?php');
        $this->writeFile('vendor/autoload.php', '<?php');
        $this->writeFile('view/install/check.php', '<main></main>');
        $this->writeFile('.env', 'APP_DEBUG=false');
        $this->writeFile('runtime/cache.tmp', 'cache');
        $this->writeFile('runtime/update/downloads/package.zip', 'package');
        $this->writeFile('public/runtime/cache.tmp', 'public runtime');
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);

        parent::tearDown();
    }

    public function test_backup_includes_managed_files_and_excludes_runtime_state(): void
    {
        $backup = (new UpdateBackupService($this->root))->backup('2.1.1', '2.1.2');

        self::assertFileExists($backup['path']);
        $entries = $this->zipEntries($backup['path']);

        self::assertContains('app/AppService.php', $entries);
        self::assertContains('config/app.php', $entries);
        self::assertContains('public/index.php', $entries);
        self::assertContains('public/index.html', $entries);
        self::assertContains('route/app.php', $entries);
        self::assertContains('vendor/autoload.php', $entries);
        self::assertContains('view/install/check.php', $entries);
        self::assertContains('.env', $entries);
        self::assertNotContains('runtime/cache.tmp', $entries);
        self::assertNotContains('runtime/update/downloads/package.zip', $entries);
        self::assertNotContains('public/runtime/cache.tmp', $entries);
    }

    private function zipEntries(string $zipPath): array
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = (string) $zip->getNameIndex($index);
        }
        $zip->close();
        sort($entries);

        return $entries;
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $contents);
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
