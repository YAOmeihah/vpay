<?php
declare(strict_types=1);

namespace tests;

use app\service\update\UpdateApplyService;
use app\service\update\UpdateStateStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UpdateApplyServiceTest extends TestCase
{
    private string $root;
    private string $packageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-update-apply-' . bin2hex(random_bytes(4));
        $this->root = $base . DIRECTORY_SEPARATOR . 'current';
        $this->packageRoot = $base . DIRECTORY_SEPARATOR . 'package';

        $this->writeFile($this->root, 'app/AppService.php', 'old code');
        $this->writeFile($this->root, 'public/index.php', 'old entry');
        $this->writeFile($this->root, 'public/runtime/cache.tmp', 'keep public runtime');
        $this->writeFile($this->root, '.env', 'APP_DEBUG=false');
        $this->writeFile($this->root, 'runtime/update/backups/current.zip', 'backup');

        $this->writeFile($this->packageRoot, 'app/AppService.php', 'new code');
        $this->writeFile($this->packageRoot, 'public/index.php', 'new entry');
        $this->writeFile($this->packageRoot, 'public/runtime/cache.tmp', 'drop package runtime');
        $this->writeFile($this->packageRoot, '.env', 'APP_DEBUG=true');
        $this->writeFile($this->packageRoot, 'config/app.php', "<?php return ['ver' => '2.1.2'];");
        $this->writeFile($this->packageRoot, 'database/migrations/.keep', '');
    }

    protected function tearDown(): void
    {
        $this->removeTree(dirname($this->root));

        parent::tearDown();
    }

    public function test_apply_copies_managed_files_preserves_env_and_calls_migration_runner(): void
    {
        $migrationCall = [];
        $service = new UpdateApplyService(
            $this->root,
            new UpdateStateStore($this->root),
            static function (string $from, string $target) use (&$migrationCall): void {
                $migrationCall = [$from, $target];
            }
        );

        $result = $service->apply([
            'from_version' => '2.1.1',
            'target_version' => '2.1.2',
            'package_root' => $this->packageRoot,
            'backup_path' => $this->root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'current.zip',
        ]);

        self::assertSame('updated', $result['status']);
        self::assertSame('new code', file_get_contents($this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'AppService.php'));
        self::assertSame('new entry', file_get_contents($this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php'));
        self::assertSame('APP_DEBUG=false', file_get_contents($this->root . DIRECTORY_SEPARATOR . '.env'));
        self::assertSame('keep public runtime', file_get_contents($this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache.tmp'));
        self::assertSame(['2.1.1', '2.1.2'], $migrationCall);
        self::assertFileDoesNotExist($this->root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'update.lock');
    }

    public function test_apply_writes_recovery_error_when_migration_fails(): void
    {
        $store = new UpdateStateStore($this->root);
        $service = new UpdateApplyService(
            $this->root,
            $store,
            static function (): void {
                throw new RuntimeException('migration failed');
            }
        );

        try {
            $service->apply([
                'from_version' => '2.1.1',
                'target_version' => '2.1.2',
                'package_root' => $this->packageRoot,
                'backup_path' => 'backup.zip',
            ]);
            self::fail('Expected migration failure');
        } catch (RuntimeException $exception) {
            self::assertSame('migration failed', $exception->getMessage());
        }

        $error = $store->lastError();
        self::assertSame('migrate', $error['stage']);
        self::assertSame('migration failed', $error['message']);
        self::assertSame('backup.zip', $error['backup_path']);
        self::assertFileDoesNotExist($store->lockPath());
    }

    private function writeFile(string $root, string $relativePath, string $contents): void
    {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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
