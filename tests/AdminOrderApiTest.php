<?php
declare(strict_types=1);

namespace tests;

use app\controller\Admin;
use app\model\MonitorTerminal;
use app\model\PayOrder;
use think\facade\Db;

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

        $request = (clone $this->app->request)
            ->withGet([
                'page' => '1',
                'limit' => '15',
            ])
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET');

        $this->app->instance('request', $request);

        $controller = new Admin($this->app);
        $response = $controller->getOrders();
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['code']);
        self::assertIsArray($payload['data']);
        self::assertSame('term-order-a', $payload['data'][0]['terminal_code'] ?? null);
        self::assertSame('订单终端A', $payload['data'][0]['terminal_snapshot'] ?? null);
    }
}
