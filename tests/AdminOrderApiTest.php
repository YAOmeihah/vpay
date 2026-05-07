<?php
declare(strict_types=1);

namespace tests;

use app\controller\admin\Order as AdminOrderController;
use app\model\MonitorTerminal;
use app\model\PayOrder;
use think\facade\Db;
use think\Request;

final class AdminOrderApiTest extends TestCase
{
    public function test_get_orders_returns_terminal_code_for_terminal_assigned_orders(): void
    {
        MonitorTerminal::create([
            'id' => 2,
            'terminal_code' => 'term-order-a',
            'terminal_name' => '订单终端A',
            'dispatch_priority' => 20,
            'status' => 'enabled',
            'online_state' => 'online',
            'monitor_key' => 'term-order-a-key',
            'last_heartbeat_at' => time(),
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.2',
            'device_meta' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        Db::name('pay_order')->insert([
            'id' => 10,
            'close_date' => 0,
            'create_date' => time(),
            'is_auto' => 0,
            'notify_url' => 'https://merchant.example/notify-order-a',
            'order_id' => 'cloud-order-a',
            'param' => '',
            'pay_date' => 0,
            'pay_id' => 'merchant-order-a',
            'pay_url' => 'weixin://order-a',
            'price' => 10.01,
            'really_price' => 10.01,
            'return_url' => 'https://merchant.example/return-order-a',
            'terminal_id' => 2,
            'channel_id' => 1,
            'assign_status' => 'assigned',
            'assign_reason' => '',
            'terminal_snapshot' => '订单终端A',
            'channel_snapshot' => '微信收款',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_WECHAT,
        ]);

        $payload = $this->requestOrders([
            'page' => '1',
            'limit' => '15',
        ]);

        self::assertSame(1, $payload['code']);
        self::assertIsArray($payload['data']);
        self::assertSame('term-order-a', $payload['data'][0]['terminal_code'] ?? null);
        self::assertSame('订单终端A', $payload['data'][0]['terminal_snapshot'] ?? null);
    }

    public function test_get_orders_filters_by_keyword_amount_date_terminal_and_channel(): void
    {
        $now = time();

        Db::name('pay_order')->insertAll([
            [
                'id' => 201,
                'close_date' => 0,
                'create_date' => $now - 3600,
                'is_auto' => 0,
                'notify_url' => 'https://merchant.example/notify-filter-a',
                'order_id' => 'cloud-filter-needle',
                'param' => '',
                'pay_date' => 0,
                'pay_id' => 'merchant-filter-a',
                'pay_url' => 'weixin://filter-a',
                'price' => 10.01,
                'really_price' => 10.01,
                'return_url' => 'https://merchant.example/return-filter-a',
                'terminal_id' => 7,
                'channel_id' => 17,
                'assign_status' => 'assigned',
                'assign_reason' => '',
                'terminal_snapshot' => '筛选终端A',
                'channel_snapshot' => '筛选通道A',
                'state' => PayOrder::STATE_PAID,
                'type' => PayOrder::TYPE_WECHAT,
            ],
            [
                'id' => 202,
                'close_date' => 0,
                'create_date' => $now - 86400 * 10,
                'is_auto' => 0,
                'notify_url' => 'https://merchant.example/notify-filter-b',
                'order_id' => 'cloud-filter-other',
                'param' => '',
                'pay_date' => 0,
                'pay_id' => 'merchant-filter-b',
                'pay_url' => 'alipay://filter-b',
                'price' => 99.99,
                'really_price' => 99.98,
                'return_url' => 'https://merchant.example/return-filter-b',
                'terminal_id' => 8,
                'channel_id' => 18,
                'assign_status' => 'assigned',
                'assign_reason' => '',
                'terminal_snapshot' => '筛选终端B',
                'channel_snapshot' => '筛选通道B',
                'state' => PayOrder::STATE_UNPAID,
                'type' => PayOrder::TYPE_ALIPAY,
            ],
        ]);

        $payload = $this->requestOrders([
            'page' => '1',
            'limit' => '15',
            'keyword' => 'needle',
            'amount' => '10.01',
            'createStart' => (string) ($now - 7200),
            'createEnd' => (string) $now,
            'terminalId' => '7',
            'channelId' => '17',
        ]);

        self::assertSame(1, $payload['code']);
        self::assertSame(1, $payload['count']);
        self::assertCount(1, $payload['data']);
        self::assertSame('cloud-filter-needle', $payload['data'][0]['order_id']);
    }

    public function test_get_orders_ignores_invalid_optional_filters(): void
    {
        Db::name('pay_order')->insert([
            'id' => 203,
            'close_date' => 0,
            'create_date' => time(),
            'is_auto' => 0,
            'notify_url' => '',
            'order_id' => 'cloud-invalid-filter',
            'param' => '',
            'pay_date' => 0,
            'pay_id' => 'merchant-invalid-filter',
            'pay_url' => '',
            'price' => 3.21,
            'really_price' => 3.21,
            'return_url' => '',
            'terminal_id' => 0,
            'channel_id' => 0,
            'assign_status' => '',
            'assign_reason' => '',
            'terminal_snapshot' => '',
            'channel_snapshot' => '',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_WECHAT,
        ]);

        $payload = $this->requestOrders([
            'page' => '1',
            'limit' => '15',
            'amount' => 'not-a-number',
            'createStart' => 'bad-start',
            'createEnd' => 'bad-end',
            'terminalId' => '0',
            'channelId' => '-1',
        ]);

        self::assertSame(1, $payload['code']);
        self::assertGreaterThanOrEqual(1, $payload['count']);
    }

    private function requestOrders(array $query): array
    {
        $request = (new Request())
            ->withGet($query)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET');

        $this->app->instance('request', $request);

        $controller = new AdminOrderController($this->app);
        $response = $controller->getOrders();

        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
