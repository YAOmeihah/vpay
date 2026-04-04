<?php
declare(strict_types=1);

namespace tests;

use app\service\cache\DashboardStatsCache;
use app\service\cache\OrderCache;
use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\order\OrderPayloadFactory;
use app\service\runtime\MonitorState;
use app\service\runtime\SettingMonitorState;
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
        $this->assertInstanceOf(SettingMonitorState::class, $this->app->make(MonitorState::class));
        $this->assertInstanceOf(OrderCache::class, $this->app->make(OrderCache::class));
        $this->assertInstanceOf(DashboardStatsCache::class, $this->app->make(DashboardStatsCache::class));
        $this->assertInstanceOf(OrderPayloadFactory::class, $this->app->make(OrderPayloadFactory::class));
    }
}
