<?php
declare(strict_types=1);

namespace tests;

use app\service\admin\AdminSettingsService;
use app\service\admin\ChannelAdminService;
use app\service\admin\DashboardStatsService;
use app\service\admin\TerminalAdminService;
use app\service\cache\DashboardStatsCache;
use app\service\cache\OrderCache;
use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\install\InstallGuardService;
use app\service\install\InstallStateService;
use app\service\order\OrderPayloadFactory;
use app\service\payment\PaymentTestLabService;
use app\service\security\KeyEncryptionService;
use app\service\security\LoginAttemptLimiter;
use PHPUnit\Framework\TestCase;
use think\App;

class ContainerBindingsTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new App(dirname(__DIR__) . DIRECTORY_SEPARATOR);
        $this->app->initialize();
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    public function test_app_container_resolves_new_abstractions(): void
    {
        $this->assertInstanceOf(SettingSystemConfig::class, $this->app->make(SystemConfig::class));
        $this->assertInstanceOf(OrderCache::class, $this->app->make(OrderCache::class));
        $this->assertInstanceOf(DashboardStatsCache::class, $this->app->make(DashboardStatsCache::class));
        $this->assertInstanceOf(OrderPayloadFactory::class, $this->app->make(OrderPayloadFactory::class));
        $this->assertInstanceOf(AdminSettingsService::class, $this->app->make(AdminSettingsService::class));
        $this->assertInstanceOf(DashboardStatsService::class, $this->app->make(DashboardStatsService::class));
        $this->assertInstanceOf(TerminalAdminService::class, $this->app->make(TerminalAdminService::class));
        $this->assertInstanceOf(ChannelAdminService::class, $this->app->make(ChannelAdminService::class));
        $this->assertInstanceOf(LoginAttemptLimiter::class, $this->app->make(LoginAttemptLimiter::class));
        $this->assertInstanceOf(KeyEncryptionService::class, $this->app->make(KeyEncryptionService::class));
        $this->assertInstanceOf(InstallStateService::class, $this->app->make(InstallStateService::class));
        $this->assertInstanceOf(InstallGuardService::class, $this->app->make(InstallGuardService::class));
        $this->assertInstanceOf(PaymentTestLabService::class, $this->app->make(PaymentTestLabService::class));
    }
}
