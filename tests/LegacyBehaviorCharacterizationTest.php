<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\CacheService;

class LegacyBehaviorCharacterizationTest extends TestCase
{
    public function test_setting_reads_and_writes_through_cache_without_changing_value_shape(): void
    {
        $key = 'legacy_characterization_key';
        CacheService::deleteSetting($key);

        $firstValue = 'legacy-value-1';
        Setting::setConfigValue($key, $firstValue);

        $this->assertSame($firstValue, Setting::getConfigValue($key));
        $this->assertSame($firstValue, CacheService::getSetting($key));

        $secondValue = 'legacy-value-updated';
        Setting::setConfigValue($key, $secondValue);

        $this->assertSame($secondValue, Setting::getConfigValue($key));
        $this->assertSame($secondValue, CacheService::getSetting($key));
    }

    public function test_order_cache_payload_shape_stays_unchanged(): void
    {
        $orderId = 'legacy-cache-order';
        CacheService::deleteOrder($orderId);
        $payload = [
            'payId' => 'legacy-order',
            'orderId' => $orderId,
            'payType' => 1,
            'price' => '99.99',
            'reallyPrice' => '99.99',
            'payUrl' => 'https://merchant.example/legacy',
            'isAuto' => 1,
            'state' => 0,
            'timeOut' => 15,
            'date' => 1690000000,
        ];

        CacheService::cacheOrder($orderId, $payload);
        $this->assertSame($payload, CacheService::getOrder($orderId));
    }
}
