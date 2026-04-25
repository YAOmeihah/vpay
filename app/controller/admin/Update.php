<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\controller\trait\ApiResponse;
use app\service\update\UpdateApplyService;
use app\service\update\UpdateBackupService;
use app\service\update\UpdatePackageService;
use app\service\update\UpdatePreflightService;
use app\service\update\UpdateReleaseService;
use app\service\update\UpdateStateStore;
use RuntimeException;

class Update extends BaseController
{
    use ApiResponse;

    public function check()
    {
        return $this->success($this->app->make(UpdateReleaseService::class)->check());
    }

    public function preflight()
    {
        $release = (array) $this->request->post('release', []);

        return $this->success($this->app->make(UpdatePreflightService::class)->check($release));
    }

    public function start()
    {
        $store = null;
        $lockAcquired = false;

        try {
            $submittedRelease = (array) $this->request->post('release', []);
            $requestedTag = (string) ($this->request->post('tag_name', '') ?: ($submittedRelease['tag_name'] ?? ''));

            $release = $this->app->make(UpdateReleaseService::class)->resolveUpdate($requestedTag);
            $tagName = (string) ($release['tag_name'] ?? '');
            $targetVersion = (string) ($release['latest_version'] ?? ltrim($tagName, 'vV'));
            $fromVersion = (string) config('app.ver');

            $preflight = $this->app->make(UpdatePreflightService::class)->check($release);
            if (($preflight['can_update'] ?? $preflight['ok'] ?? false) !== true) {
                throw new RuntimeException($this->preflightFailureMessage((array) ($preflight['checks'] ?? [])));
            }

            $store = $this->app->make(UpdateStateStore::class);
            $lockAcquired = $store->acquireLock([
                'stage' => 'download',
                'from_version' => $fromVersion,
                'target_version' => $targetVersion,
                'started_at' => time(),
            ]);
            if (!$lockAcquired) {
                throw new RuntimeException('当前已有更新任务正在执行');
            }

            $store->writeStatus(['stage' => 'download', 'message' => '正在下载并校验更新包']);
            $package = $this->app->make(UpdatePackageService::class)->download(
                (array) ($release['assets'] ?? []),
                $tagName
            );

            $store->writeStatus(['stage' => 'backup', 'message' => '正在备份当前程序文件']);
            $backup = $this->app->make(UpdateBackupService::class)->backup($fromVersion, $targetVersion);
            $result = $this->app->make(UpdateApplyService::class)->apply($package + [
                'from_version' => $fromVersion,
                'target_version' => $targetVersion,
                'backup_path' => $backup['path'] ?? '',
            ]);

            return $this->success($result, '更新完成');
        } catch (\Throwable $exception) {
            return $this->error($exception->getMessage());
        } finally {
            if ($lockAcquired && is_object($store) && method_exists($store, 'clearLock')) {
                $store->clearLock();
            }
        }
    }

    public function status()
    {
        return $this->success($this->app->make(UpdateStateStore::class)->status());
    }

    public function recover()
    {
        return $this->success($this->app->make(UpdateStateStore::class)->lastError());
    }

    private function preflightFailureMessage(array $checks): string
    {
        foreach ($checks as $check) {
            if (is_array($check) && ($check['ok'] ?? false) !== true) {
                return (string) ($check['message'] ?? '环境预检未通过');
            }
        }

        return '环境预检未通过';
    }
}
