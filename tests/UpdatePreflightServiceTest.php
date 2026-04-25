<?php
declare(strict_types=1);

namespace tests;

use app\service\update\UpdatePreflightService;
use app\service\update\UpdateStateStore;
use PHPUnit\Framework\TestCase;

final class UpdatePreflightServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-update-preflight-' . bin2hex(random_bytes(4));
        foreach (['app', 'config', 'database', 'route', 'vendor', 'view', 'public', 'runtime/install'] as $dir) {
            mkdir($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir), 0777, true);
        }
        file_put_contents($this->root . DIRECTORY_SEPARATOR . '.env', 'APP_DEBUG=false');
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);

        parent::tearDown();
    }

    public function test_preflight_passes_when_required_paths_are_writable_and_no_locks_exist(): void
    {
        $service = new UpdatePreflightService($this->root, new UpdateStateStore($this->root));

        $result = $service->check(['zip_size' => 1024]);

        self::assertTrue($result['ok']);
        self::assertTrue($result['can_update']);
        self::assertSame([], array_values(array_filter($result['checks'], static fn (array $check): bool => $check['ok'] !== true)));
    }

    public function test_preflight_allows_missing_optional_extend_directory_when_root_is_writable(): void
    {
        self::assertDirectoryDoesNotExist($this->root . DIRECTORY_SEPARATOR . 'extend');

        $result = (new UpdatePreflightService($this->root, new UpdateStateStore($this->root)))->check(['zip_size' => 1024]);

        self::assertTrue($result['ok']);
        self::assertTrue($result['can_update']);
        self::assertContains('extend 可写', array_column($result['checks'], 'label'));
    }

    public function test_preflight_checks_real_program_file_write_access(): void
    {
        $result = (new UpdatePreflightService($this->root, new UpdateStateStore($this->root)))->check(['zip_size' => 1024]);

        self::assertContains('程序文件可写', array_column($result['checks'], 'label'));
    }

    public function test_preflight_fails_when_nested_program_directory_is_not_writable(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Windows chmod does not reliably simulate web user write permissions');
        }

        $nested = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'service' . DIRECTORY_SEPARATOR . 'update';
        mkdir($nested, 0555, true);
        chmod($nested, 0555);

        try {
            $result = (new UpdatePreflightService($this->root, new UpdateStateStore($this->root)))->check(['zip_size' => 1024]);
        } finally {
            chmod($nested, 0777);
        }

        self::assertFalse($result['ok']);
        self::assertContains('程序文件存在不可写路径', array_column($result['checks'], 'message'));
    }

    public function test_preflight_reads_zip_size_from_release_assets(): void
    {
        $free = @disk_free_space($this->root);
        if ($free === false) {
            self::markTestSkipped('disk_free_space is unavailable');
        }

        $result = (new UpdatePreflightService($this->root, new UpdateStateStore($this->root)))->check([
            'assets' => [
                'zip' => [
                    'size' => ((int) $free) + 1,
                ],
            ],
        ]);

        self::assertFalse($result['ok']);
        self::assertFalse($result['can_update']);
        self::assertContains('磁盘剩余空间不足', array_column($result['checks'], 'message'));
    }

    public function test_preflight_fails_when_update_lock_exists(): void
    {
        $store = new UpdateStateStore($this->root);
        $store->writeLock(['stage' => 'apply']);

        $result = (new UpdatePreflightService($this->root, $store))->check(['zip_size' => 1024]);

        self::assertFalse($result['ok']);
        self::assertContains('当前已有更新任务正在执行', array_column($result['checks'], 'message'));
    }

    public function test_state_store_writes_and_reads_last_error(): void
    {
        $store = new UpdateStateStore($this->root);
        $store->writeError(['stage' => 'download', 'message' => '网络失败']);

        self::assertSame('download', $store->lastError()['stage']);
        self::assertSame('网络失败', $store->lastError()['message']);
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
