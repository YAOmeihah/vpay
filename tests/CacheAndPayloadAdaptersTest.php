<?php
declare(strict_types=1);

namespace tests;

use app\model\PayOrder;
use app\service\CacheService;
use app\service\cache\DashboardStatsCache;
use app\service\cache\OrderCache;
use app\service\order\OrderPayloadFactory;
use PHPUnit\Framework\TestCase;
use think\App;

class CacheAndPayloadAdaptersTest extends TestCase
{
    private static App $app;
    private static string $rootPath;
    private static string $cachePath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        self::$app = new App(self::$rootPath);
        self::$app->initialize();
        self::configureCache();
    }

    protected function tearDown(): void
    {
        CacheService::clearAll();
        parent::tearDown();
    }

    private static function configureCache(): void
    {
        self::$cachePath = self::$rootPath . 'runtime' . DIRECTORY_SEPARATOR . 'phpunit-cache-lite' . DIRECTORY_SEPARATOR;
        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0777, true);
        }

        $cacheConfig = self::$app->config->get('cache');
        $cacheConfig['default'] = 'file';
        $cacheConfig['stores']['file']['path'] = self::$cachePath;

        self::$app->config->set($cacheConfig, 'cache');
        CacheService::clearAll();
    }

    public function test_order_payload_factory_preserves_legacy_key_order_and_values(): void
    {
        if (!class_exists(OrderPayloadFactory::class)) {
            $this->fail('OrderPayloadFactory class is missing.');
        }

        $factory = new OrderPayloadFactory();
        $payload = $factory->create(
            'merchant-001',
            'order-202404040001',
            PayOrder::TYPE_WECHAT,
            12.34,
            '12.35',
            'weixin://pay-url',
            1,
            PayOrder::STATE_UNPAID,
            '15',
            1700000000
        );

        $expectedKeys = [
            'payId',
            'orderId',
            'payType',
            'price',
            'reallyPrice',
            'payUrl',
            'isAuto',
            'state',
            'timeOut',
            'date',
        ];

        $this->assertSame($expectedKeys, array_keys($payload));
        $this->assertSame([
            'payId' => 'merchant-001',
            'orderId' => 'order-202404040001',
            'payType' => PayOrder::TYPE_WECHAT,
            'price' => 12.34,
            'reallyPrice' => '12.35',
            'payUrl' => 'weixin://pay-url',
            'isAuto' => 1,
            'state' => PayOrder::STATE_UNPAID,
            'timeOut' => '15',
            'date' => 1700000000,
        ], $payload);
    }

    public function test_cache_adapters_keep_existing_cache_keys_and_payloads(): void
    {
        if (!class_exists(OrderCache::class)) {
            $this->fail('OrderCache class is missing.');
        }

        if (!class_exists(DashboardStatsCache::class)) {
            $this->fail('DashboardStatsCache class is missing.');
        }

        $orderCache = new OrderCache();
        $dashboardCache = new DashboardStatsCache();

        $orderId = 'order-202404040002';
        $orderPayload = [
            'payId' => 'merchant-002',
            'orderId' => $orderId,
            'payType' => PayOrder::TYPE_ALIPAY,
            'price' => '55.00',
            'reallyPrice' => '55.01',
            'payUrl' => 'alipays://pay-url',
            'isAuto' => 0,
            'state' => PayOrder::STATE_UNPAID,
            'timeOut' => '30',
            'date' => 1700000100,
        ];

        $this->assertTrue($orderCache->cacheOrder($orderId, $orderPayload));
        $this->assertSame($orderPayload, CacheService::getOrder($orderId));
        $this->assertSame($orderPayload, $orderCache->getOrder($orderId));

        $dashboardStats = [
            'orders' => 12,
            'amount' => '155.00',
            'pending' => 4,
        ];

        $this->assertTrue($dashboardCache->cacheStats($dashboardStats));
        $this->assertSame($dashboardStats, CacheService::getStats('dashboard'));
        $this->assertSame($dashboardStats, $dashboardCache->getStats());

        $this->assertTrue($orderCache->deleteOrder($orderId));
        $this->assertNull(CacheService::getOrder($orderId));

        $this->assertTrue($dashboardCache->deleteStats());
        $this->assertNull(CacheService::getStats('dashboard'));
    }
}
