<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\AdminSettingsService;

class Settings extends BaseController
{
    use \app\controller\trait\ApiResponse;

    /**
     * 获取系统设置
     */
    public function getSettings()
    {
        return $this->success($this->adminSettingsService()->getSettings());
    }

    /**
     * 保存系统设置
     */
    public function saveSetting()
    {
        $this->adminSettingsService()->saveSettings($this->request->param());

        return $this->success();
    }

    private function adminSettingsService(): AdminSettingsService
    {
        return $this->app->make(AdminSettingsService::class);
    }
}
