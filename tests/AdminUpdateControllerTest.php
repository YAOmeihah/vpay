<?php
declare(strict_types=1);

namespace tests;

use app\controller\admin\Update;
use app\service\update\UpdateApplyService;
use app\service\update\UpdateBackupService;
use app\service\update\UpdatePackageService;
use app\service\update\UpdatePreflightService;
use app\service\update\UpdateReleaseService;
use app\service\update\UpdateStateStore;

final class AdminUpdateControllerTest extends TestCase
{
    public function test_check_returns_release_service_payload(): void
    {
        $this->bindFresh(UpdateReleaseService::class, fn () => new class {
            public function check(): array
            {
                return [
                    'status' => 'up_to_date',
                    'current_version' => '2.1.1',
                ];
            }
        });

        $payload = $this->decode((new Update($this->app))->check());

        self::assertSame(1, $payload['code']);
        self::assertSame('up_to_date', $payload['data']['status']);
        self::assertSame('2.1.1', $payload['data']['current_version']);
    }

    public function test_preflight_passes_release_payload_to_service(): void
    {
        $this->withPostRequest([
            'release' => [
                'tag_name' => 'v2.1.2',
                'assets' => [],
            ],
        ]);

        $this->bindFresh(UpdatePreflightService::class, fn () => new class {
            public function check(array $release): array
            {
                return [
                    'can_update' => $release['tag_name'] === 'v2.1.2',
                    'checks' => [
                        ['label' => 'ZipArchive', 'ok' => true, 'message' => '可用'],
                    ],
                ];
            }
        });

        $payload = $this->decode((new Update($this->app))->preflight());

        self::assertSame(1, $payload['code']);
        self::assertTrue($payload['data']['can_update']);
        self::assertSame('ZipArchive', $payload['data']['checks'][0]['label']);
    }

    public function test_start_downloads_backs_up_and_applies_release(): void
    {
        $currentVersion = (string) config('app.ver');
        $targetVersion = '9.9.9';
        $tagName = 'v' . $targetVersion;
        $this->withPostRequest([
            'release' => [
                'tag_name' => $tagName,
                'assets' => [
                    'zip' => ['name' => 'vpay-' . $tagName . '.zip', 'download_url' => 'https://evil.example/vpay.zip'],
                    'sha256' => ['name' => 'vpay-' . $tagName . '.zip.sha256', 'download_url' => 'https://evil.example/vpay.zip.sha256'],
                ],
            ],
        ]);
        $log = (object) ['steps' => []];

        $this->bindFresh(UpdateReleaseService::class, fn () => new class($log, $tagName) {
            public function __construct(private readonly object $log, private readonly string $tagName)
            {
            }

            public function resolveUpdate(string $requestedTag): array
            {
                $this->log->steps[] = ['resolve', $requestedTag];

                return [
                    'status' => 'update_available',
                    'tag_name' => $this->tagName,
                    'latest_version' => ltrim($this->tagName, 'vV'),
                    'assets' => [
                        'zip' => [
                            'name' => 'vpay-' . $this->tagName . '.zip',
                            'download_url' => 'https://github.com/YAOmeihah/vpay/releases/download/' . $this->tagName . '/vpay-' . $this->tagName . '.zip',
                            'size' => 1024,
                        ],
                        'sha256' => [
                            'name' => 'vpay-' . $this->tagName . '.zip.sha256',
                            'download_url' => 'https://github.com/YAOmeihah/vpay/releases/download/' . $this->tagName . '/vpay-' . $this->tagName . '.zip.sha256',
                            'size' => 128,
                        ],
                    ],
                ];
            }
        });
        $this->bindFresh(UpdatePreflightService::class, fn () => new class($log) {
            public function __construct(private readonly object $log)
            {
            }

            public function check(array $release): array
            {
                $this->log->steps[] = ['preflight', $release['tag_name'] ?? ''];

                return ['can_update' => true, 'checks' => []];
            }
        });
        $this->bindFresh(UpdateStateStore::class, fn () => new class($log) {
            private bool $locked = false;

            public function __construct(private readonly object $log)
            {
            }

            public function acquireLock(array $payload): bool
            {
                $this->log->steps[] = ['lock', $payload['stage'] ?? ''];
                if ($this->locked) {
                    return false;
                }
                $this->locked = true;

                return true;
            }

            public function writeStatus(array $payload): void
            {
                $this->log->steps[] = ['status', $payload['stage'] ?? ''];
            }

            public function writeLock(array $payload): void
            {
                $this->log->steps[] = ['lock-stage', $payload['stage'] ?? ''];
            }

            public function clearLock(): void
            {
                $this->log->steps[] = ['clear-lock'];
                $this->locked = false;
            }
        });
        $this->bindFresh(UpdatePackageService::class, fn () => new class($log) {
            public function __construct(private readonly object $log)
            {
            }

            public function download(array $assets, string $tagName): array
            {
                $this->log->steps[] = ['download', $tagName, $assets['zip']['download_url'] ?? null];

                return [
                    'tag_name' => $tagName,
                    'package_root' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-package',
                ];
            }
        });
        $this->bindFresh(UpdateBackupService::class, fn () => new class($log) {
            public function __construct(private readonly object $log)
            {
            }

            public function backup(string $fromVersion, string $targetVersion): array
            {
                $this->log->steps[] = ['backup', $fromVersion, $targetVersion];

                return ['path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-backup.zip'];
            }
        });
        $this->bindFresh(UpdateApplyService::class, fn () => new class($log) {
            public function __construct(private readonly object $log)
            {
            }

            public function apply(array $context): array
            {
                $this->log->steps[] = [
                    'apply',
                    $context['from_version'] ?? '',
                    $context['target_version'] ?? '',
                    $context['backup_path'] ?? '',
                    $context['package_root'] ?? '',
                ];

                return [
                    'status' => 'updated',
                    'from_version' => (string) ($context['from_version'] ?? ''),
                    'target_version' => (string) ($context['target_version'] ?? ''),
                    'backup_path' => (string) ($context['backup_path'] ?? ''),
                ];
            }
        });

        $payload = $this->decode((new Update($this->app))->start());

        self::assertSame(1, $payload['code']);
        self::assertSame('更新完成', $payload['msg']);
        self::assertSame('updated', $payload['data']['status']);
        self::assertSame($targetVersion, $payload['data']['target_version']);
        self::assertSame([
            ['resolve', $tagName],
            ['preflight', $tagName],
            ['lock', 'download'],
            ['status', 'download'],
            ['download', $tagName, 'https://github.com/YAOmeihah/vpay/releases/download/' . $tagName . '/vpay-' . $tagName . '.zip'],
            ['status', 'backup'],
            ['backup', $currentVersion, $targetVersion],
            [
                'apply',
                $currentVersion,
                $targetVersion,
                sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-backup.zip',
                sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-package',
            ],
            ['clear-lock'],
        ], $log->steps);
    }

    public function test_start_refuses_to_run_when_update_lock_is_busy(): void
    {
        $this->withPostRequest([
            'release' => [
                'tag_name' => 'v9.9.9',
            ],
        ]);
        $log = (object) ['downloaded' => false];

        $this->bindFresh(UpdateReleaseService::class, fn () => new class {
            public function resolveUpdate(string $requestedTag): array
            {
                return [
                    'status' => 'update_available',
                    'tag_name' => $requestedTag,
                    'latest_version' => ltrim($requestedTag, 'vV'),
                    'assets' => [
                        'zip' => ['download_url' => 'https://github.com/YAOmeihah/vpay/releases/download/v9.9.9/vpay-v9.9.9.zip', 'size' => 1024],
                        'sha256' => ['download_url' => 'https://github.com/YAOmeihah/vpay/releases/download/v9.9.9/vpay-v9.9.9.zip.sha256', 'size' => 128],
                    ],
                ];
            }
        });
        $this->bindFresh(UpdatePreflightService::class, fn () => new class {
            public function check(array $release): array
            {
                return ['can_update' => true, 'checks' => []];
            }
        });
        $this->bindFresh(UpdateStateStore::class, fn () => new class {
            public function acquireLock(array $payload): bool
            {
                return false;
            }

            public function clearLock(): void
            {
            }
        });
        $this->bindFresh(UpdatePackageService::class, fn () => new class($log) {
            public function __construct(private readonly object $log)
            {
            }

            public function download(array $assets, string $tagName): array
            {
                $this->log->downloaded = true;

                return [];
            }
        });

        $payload = $this->decode((new Update($this->app))->start());

        self::assertSame(-1, $payload['code']);
        self::assertSame('当前已有更新任务正在执行', $payload['msg']);
        self::assertFalse($log->downloaded);
    }

    public function test_start_returns_api_error_when_update_fails(): void
    {
        $this->withPostRequest([
            'release' => [
                'tag_name' => 'v2.1.2',
                'assets' => [],
            ],
        ]);

        $this->bindFresh(UpdateReleaseService::class, fn () => new class {
            public function resolveUpdate(string $requestedTag): array
            {
                return [
                    'status' => 'update_available',
                    'tag_name' => $requestedTag,
                    'latest_version' => ltrim($requestedTag, 'vV'),
                    'assets' => [
                        'zip' => ['download_url' => 'https://github.com/YAOmeihah/vpay/releases/download/v2.1.2/vpay-v2.1.2.zip', 'size' => 1024],
                        'sha256' => ['download_url' => 'https://github.com/YAOmeihah/vpay/releases/download/v2.1.2/vpay-v2.1.2.zip.sha256', 'size' => 128],
                    ],
                ];
            }
        });
        $this->bindFresh(UpdatePreflightService::class, fn () => new class {
            public function check(array $release): array
            {
                return ['can_update' => true, 'checks' => []];
            }
        });
        $this->bindFresh(UpdateStateStore::class, fn () => new class {
            public function acquireLock(array $payload): bool
            {
                return true;
            }

            public function writeStatus(array $payload): void
            {
            }

            public function clearLock(): void
            {
            }
        });
        $this->bindFresh(UpdatePackageService::class, fn () => new class {
            public function download(array $assets, string $tagName): array
            {
                throw new \RuntimeException('下载失败');
            }
        });

        $payload = $this->decode((new Update($this->app))->start());

        self::assertSame(-1, $payload['code']);
        self::assertSame('下载失败', $payload['msg']);
        self::assertNull($payload['data']);
    }

    public function test_status_and_recover_return_state_store_payloads(): void
    {
        $this->bindFresh(UpdateStateStore::class, fn () => new class {
            public function status(): array
            {
                return ['stage' => 'copy', 'message' => '正在覆盖程序文件'];
            }

            public function lastError(): array
            {
                return ['stage' => 'migrate', 'message' => 'SQL 执行失败'];
            }
        });

        $statusPayload = $this->decode((new Update($this->app))->status());
        $recoverPayload = $this->decode((new Update($this->app))->recover());

        self::assertSame(1, $statusPayload['code']);
        self::assertSame('copy', $statusPayload['data']['stage']);
        self::assertSame(1, $recoverPayload['code']);
        self::assertSame('SQL 执行失败', $recoverPayload['data']['message']);
    }

    public function test_admin_routes_point_to_update_controller(): void
    {
        $routes = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'route' . DIRECTORY_SEPARATOR . 'admin.php');

        self::assertIsString($routes);
        self::assertStringContainsString("Route::any('checkUpdate', 'admin.Update/check');", $routes);
        self::assertStringContainsString("Route::post('preflightUpdate', 'admin.Update/preflight');", $routes);
        self::assertStringContainsString("Route::post('startUpdate', 'admin.Update/start');", $routes);
        self::assertStringContainsString("Route::any('getUpdateStatus', 'admin.Update/status');", $routes);
        self::assertStringContainsString("Route::any('getUpdateRecovery', 'admin.Update/recover');", $routes);
        self::assertStringNotContainsString("Route::any('checkUpdate', 'admin/checkUpdate');", $routes);
    }

    private function withPostRequest(array $post): void
    {
        $request = (clone $this->app->request)
            ->withPost($post)
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');

        $this->app->instance('request', $request);
    }

    private function bindFresh(string $abstract, callable $factory): void
    {
        $this->app->delete($abstract);
        $this->app->bind($abstract, $factory);
    }

    /**
     * @return array{code:int,msg:string,data:mixed}
     */
    private function decode(\think\response\Json $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
