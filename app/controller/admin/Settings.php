<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\AdminSettingsService;
use app\service\notification\NotificationService;

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

    public function generateMaintenanceToken()
    {
        return $this->success([
            'token' => $this->adminSettingsService()->generateMaintenanceToken(),
        ]);
    }

    public function testMaintenanceNotification()
    {
        $ok = $this->app->make(NotificationService::class)->sendTestMessage();

        if (!$ok) {
            return $this->error('Telegram 测试推送失败，请检查是否启用并配置 Bot Token 与 Chat ID');
        }

        return $this->success();
    }

    private function adminSettingsService(): AdminSettingsService
    {
        return $this->app->make(AdminSettingsService::class);
    }
}
