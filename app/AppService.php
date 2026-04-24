<?php
declare (strict_types = 1);

namespace app;

use app\service\cache\DashboardStatsCache;
use app\service\cache\OrderCache;
use app\service\admin\AdminPermissionService;
use app\service\config\SettingConfigRepository;
use app\service\config\SettingSystemConfig;
use app\service\install\InstallGuardService;
use app\service\install\InstallStateService;
use app\service\config\SystemConfig;
use app\service\order\OrderPayloadFactory;
use think\Service;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register()
    {
        $this->app->bind(SystemConfig::class, SettingSystemConfig::class);
        $this->app->bind(SettingConfigRepository::class, SettingConfigRepository::class);
        $this->app->bind(AdminPermissionService::class, AdminPermissionService::class);
        $this->app->bind(OrderCache::class, OrderCache::class);
        $this->app->bind(DashboardStatsCache::class, DashboardStatsCache::class);
        $this->app->bind(OrderPayloadFactory::class, OrderPayloadFactory::class);
        $this->app->bind(InstallStateService::class, InstallStateService::class);
        $this->app->bind(InstallGuardService::class, InstallGuardService::class);
    }

    public function boot()
    {
        // 服务启动
    }
}
