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
        try {
            $release = (array) $this->request->post('release', []);
            $tagName = (string) ($release['tag_name'] ?? '');
            $targetVersion = ltrim($tagName, 'vV');
            $fromVersion = (string) config('app.ver');

            $package = $this->app->make(UpdatePackageService::class)->download(
                (array) ($release['assets'] ?? []),
                $tagName
            );
            $backup = $this->app->make(UpdateBackupService::class)->backup($fromVersion, $targetVersion);
            $result = $this->app->make(UpdateApplyService::class)->apply($package + [
                'from_version' => $fromVersion,
                'target_version' => $targetVersion,
                'backup_path' => $backup['path'] ?? '',
            ]);

            return $this->success($result, '更新完成');
        } catch (\Throwable $exception) {
            return $this->error($exception->getMessage());
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
}
