<?php
declare (strict_types = 1);

namespace app;

use app\service\admin\AdminSettingsService;
use app\service\admin\ChannelAdminService;
use app\service\admin\DashboardStatsService;
use app\service\admin\TerminalAdminService;
use app\service\cache\DashboardStatsCache;
use app\service\cache\OrderCache;
use app\service\admin\AdminPermissionService;
use app\service\config\SettingConfigRepository;
use app\service\config\SettingSystemConfig;
use app\service\install\InstallGuardService;
use app\service\install\InstallStateService;
use app\service\config\SystemConfig;
use app\service\order\OrderPayloadFactory;
use app\service\payment\PaymentTestLabService;
use app\service\security\KeyEncryptionService;
use app\service\security\LoginAttemptLimiter;
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
        $this->app->bind(AdminSettingsService::class, AdminSettingsService::class);
        $this->app->bind(DashboardStatsService::class, DashboardStatsService::class);
        $this->app->bind(TerminalAdminService::class, TerminalAdminService::class);
        $this->app->bind(ChannelAdminService::class, ChannelAdminService::class);
        $this->app->bind(OrderCache::class, OrderCache::class);
        $this->app->bind(DashboardStatsCache::class, DashboardStatsCache::class);
        $this->app->bind(OrderPayloadFactory::class, OrderPayloadFactory::class);
        $this->app->bind(InstallStateService::class, InstallStateService::class);
        $this->app->bind(InstallGuardService::class, InstallGuardService::class);
        $this->app->bind(LoginAttemptLimiter::class, LoginAttemptLimiter::class);
        $this->app->bind(KeyEncryptionService::class, KeyEncryptionService::class);
        $this->app->bind(PaymentTestLabService::class, PaymentTestLabService::class);
    }

    public function boot()
    {
        // 服务启动
    }
}
