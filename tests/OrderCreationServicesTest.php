<?php
declare(strict_types=1);

namespace tests;

use app\model\MonitorTerminal;
use app\model\PaymentEvent;
use app\model\PayOrder;
use app\model\TerminalChannel;
use app\model\TmpPrice;
use app\service\CacheService;
use app\service\OrderService;

class OrderCreationServicesTest extends TestCase
{
    public function test_native_order_service_keeps_original_payload_shape_and_uses_qrcode_override(): void
    {
        $this->insertQrcode(PayOrder::TYPE_WECHAT, 10.00, 'weixin://matched-qrcode');

        $result = OrderService::createOrder([
            'payId' => 'native-order-001',
            'type' => PayOrder::TYPE_WECHAT,
            'price' => '10.00',
            'param' => 'native-param',
            'notifyUrl' => 'https://merchant.example/notify/native',
            'returnUrl' => 'https://merchant.example/return/native',
        ]);

        $this->assertSame(
            [
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
                'terminalId',
                'channelId',
                'terminalSnapshot',
                'channelSnapshot',
            ],
            array_keys($result)
        );
        $this->assertSame('native-order-001', $result['payId']);
        $this->assertSame(PayOrder::TYPE_WECHAT, $result['payType']);
        $this->assertSame('10.00', $result['price']);
        $this->assertSame('10.00', $result['reallyPrice']);
        $this->assertSame('weixin://matched-qrcode', $result['payUrl']);
        $this->assertSame(0, $result['isAuto']);
        $this->assertSame(1, $result['terminalId']);
        $this->assertSame(1, $result['channelId']);
        $this->assertSame('默认终端', $result['terminalSnapshot']);
        $this->assertSame('默认微信通道', $result['channelSnapshot']);

        $order = PayOrder::where('pay_id', 'native-order-001')->findOrFail();
        $this->assertSame('native-param', $order->getAttr('param'));
        $this->assertSame('weixin://matched-qrcode', $order->getAttr('pay_url'));
        $this->assertSame(1, $order->getAttr('terminal_id'));
        $this->assertSame(1, $order->getAttr('channel_id'));

        $this->assertSame($result, CacheService::getOrder($result['orderId']));
    }

    public function test_native_order_service_assigns_default_terminal_and_channel_metadata(): void
    {
        $result = OrderService::createOrder([
            'payId' => 'native-order-002',
            'type' => PayOrder::TYPE_WECHAT,
            'price' => '20.00',
            'param' => 'native-param-2',
            'notifyUrl' => 'https://merchant.example/notify/native-2',
            'returnUrl' => 'https://merchant.example/return/native-2',
        ]);

        $this->assertSame(1, $result['terminalId']);
        $this->assertSame(1, $result['channelId']);
        $this->assertSame('默认终端', $result['terminalSnapshot']);
        $this->assertSame('默认微信通道', $result['channelSnapshot']);

        $order = PayOrder::where('pay_id', 'native-order-002')->findOrFail();
        $this->assertSame(1, $order->getAttr('terminal_id'));
        $this->assertSame(1, $order->getAttr('channel_id'));
        $this->assertSame('默认终端', $order->getAttr('terminal_snapshot'));
        $this->assertSame('默认微信通道', $order->getAttr('channel_snapshot'));
    }

    public function test_handle_pay_push_can_record_multiple_unmatched_transfers_under_unique_indexes(): void
    {
        $first = OrderService::handleTerminalPayPush(
            1,
            '66.66',
            PayOrder::TYPE_WECHAT,
            'evt-unmatched-1',
            ['terminalCode' => 'default-terminal']
        );
        $second = OrderService::handleTerminalPayPush(
            1,
            '77.77',
            PayOrder::TYPE_WECHAT,
            'evt-unmatched-2',
            ['terminalCode' => 'default-terminal']
        );

        $this->assertSame([
            'matched' => false,
            'alreadyProcessed' => false,
            'notifyOk' => true,
            'notifyDetail' => '',
        ], $first);
        $this->assertSame([
            'matched' => false,
            'alreadyProcessed' => false,
            'notifyOk' => true,
            'notifyDetail' => '',
        ], $second);

        $firstRow = PayOrder::where('price', '66.66')->findOrFail();
        $secondRow = PayOrder::where('price', '77.77')->findOrFail();

        $this->assertSame('无订单转账', $firstRow->getAttr('param'));
        $this->assertSame('无订单转账', $secondRow->getAttr('param'));
        $this->assertSame(1, $firstRow->getAttr('terminal_id'));
        $this->assertSame(1, $secondRow->getAttr('terminal_id'));
        $this->assertNotSame($firstRow->getAttr('order_id'), $secondRow->getAttr('order_id'));
        $this->assertNotSame($firstRow->getAttr('pay_id'), $secondRow->getAttr('pay_id'));
    }

    public function test_handle_pay_push_matches_only_orders_assigned_to_the_target_terminal(): void
    {
        $this->assertTrue(
            method_exists(OrderService::class, 'handleTerminalPayPush'),
            'Multi-terminal callbacks need a terminal-scoped order matcher.'
        );

        PayOrder::create([
            'close_date' => 0,
            'create_date' => time(),
            'is_auto' => 0,
            'notify_url' => 'https://merchant.example/notify/a',
            'order_id' => 'order-terminal-a',
            'param' => 'term-a',
            'pay_date' => 0,
            'pay_id' => 'merchant-terminal-a',
            'pay_url' => 'weixin://pay/a',
            'price' => '30.00',
            'really_price' => '30.00',
            'return_url' => 'https://merchant.example/return/a',
            'terminal_id' => 11,
            'channel_id' => 21,
            'terminal_snapshot' => '终端 A',
            'channel_snapshot' => '微信 A',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_WECHAT,
        ]);
        PayOrder::create([
            'close_date' => 0,
            'create_date' => time(),
            'is_auto' => 0,
            'notify_url' => 'https://merchant.example/notify/b',
            'order_id' => 'order-terminal-b',
            'param' => 'term-b',
            'pay_date' => 0,
            'pay_id' => 'merchant-terminal-b',
            'pay_url' => 'weixin://pay/b',
            'price' => '30.00',
            'really_price' => '30.00',
            'return_url' => 'https://merchant.example/return/b',
            'terminal_id' => 12,
            'channel_id' => 22,
            'terminal_snapshot' => '终端 B',
            'channel_snapshot' => '微信 B',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_WECHAT,
        ]);

        $result = OrderService::handleTerminalPayPush(
            12,
            '30.00',
            PayOrder::TYPE_WECHAT,
            'evt-terminal-b',
            ['terminalCode' => 'term-b']
        );

        $this->assertTrue($result['matched']);
        $this->assertFalse($result['alreadyProcessed']);

        $orderA = PayOrder::where('pay_id', 'merchant-terminal-a')->findOrFail();
        $orderB = PayOrder::where('pay_id', 'merchant-terminal-b')->findOrFail();
        $event = PaymentEvent::where('event_id', 'evt-terminal-b')->findOrFail();

        $this->assertSame(PayOrder::STATE_UNPAID, $orderA->getAttr('state'));
        $this->assertContains($orderB->getAttr('state'), [PayOrder::STATE_PAID, PayOrder::STATE_NOTIFY_FAILED]);
        $this->assertSame(12, $event->getAttr('terminal_id'));
        $this->assertSame(22, $event->getAttr('channel_id'));
        $this->assertSame('order-terminal-b', $event->getAttr('matched_order_id'));
        $this->assertSame('matched', $event->getAttr('result'));
    }

    public function test_create_order_falls_back_to_next_eligible_terminal_when_high_priority_channel_is_unusable(): void
    {
        TerminalChannel::where('id', 1)->update([
            'pay_url' => '',
            'updated_at' => time(),
        ]);

        MonitorTerminal::create([
            'id' => 2,
            'terminal_code' => 'fallback-terminal',
            'terminal_name' => '回退终端',
            'dispatch_priority' => 20,
            'status' => 'enabled',
            'online_state' => 'online',
            'monitor_key' => 'fallback-terminal-key',
            'last_heartbeat_at' => time(),
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.2',
            'device_meta' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        TerminalChannel::create([
            'id' => 3,
            'terminal_id' => 2,
            'type' => PayOrder::TYPE_WECHAT,
            'channel_name' => '回退微信通道',
            'status' => 'enabled',
            'pay_url' => 'weixin://fallback-pay-url',
            'last_used_at' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $result = OrderService::createOrder([
            'payId' => 'native-order-fallback-001',
            'type' => PayOrder::TYPE_WECHAT,
            'price' => '18.00',
            'param' => 'fallback-check',
            'notifyUrl' => 'https://merchant.example/notify/fallback',
            'returnUrl' => 'https://merchant.example/return/fallback',
        ]);

        $this->assertSame(2, $result['terminalId']);
        $this->assertSame(3, $result['channelId']);
        $this->assertSame('回退终端', $result['terminalSnapshot']);
        $this->assertSame('回退微信通道', $result['channelSnapshot']);
        $this->assertSame('weixin://fallback-pay-url', $result['payUrl']);
    }

    public function test_round_robin_single_terminal_keeps_accepting_repeated_orders(): void
    {
        $this->seedSettings(['allocationStrategy' => 'round_robin']);

        $first = OrderService::createOrder([
            'payId' => 'single-terminal-001',
            'type' => PayOrder::TYPE_WECHAT,
            'price' => '31.00',
            'param' => 'single-terminal',
            'notifyUrl' => 'https://merchant.example/notify/single-1',
            'returnUrl' => 'https://merchant.example/return/single-1',
        ]);
        $second = OrderService::createOrder([
            'payId' => 'single-terminal-002',
            'type' => PayOrder::TYPE_WECHAT,
            'price' => '31.00',
            'param' => 'single-terminal',
            'notifyUrl' => 'https://merchant.example/notify/single-2',
            'returnUrl' => 'https://merchant.example/return/single-2',
        ]);
        $third = OrderService::createOrder([
            'payId' => 'single-terminal-003',
            'type' => PayOrder::TYPE_WECHAT,
            'price' => '31.00',
            'param' => 'single-terminal',
            'notifyUrl' => 'https://merchant.example/notify/single-3',
            'returnUrl' => 'https://merchant.example/return/single-3',
        ]);

        $this->assertSame([1, 1, 1], [
            $first['channelId'],
            $second['channelId'],
            $third['channelId'],
        ]);
        $this->assertSame(['31.00', '31.01', '31.02'], [
            $first['reallyPrice'],
            $second['reallyPrice'],
            $third['reallyPrice'],
        ]);
    }

    public function test_round_robin_rotates_across_available_terminals_for_rapid_consecutive_orders(): void
    {
        $this->seedSettings(['allocationStrategy' => 'round_robin']);
        $this->createWechatTerminalWithChannel(2, 3, 'terminal-b', '终端 B', 20, 'weixin://pay/b');
        $this->createWechatTerminalWithChannel(3, 4, 'terminal-c', '终端 C', 30, 'weixin://pay/c');

        $channelIds = [];
        for ($index = 1; $index <= 5; $index++) {
            $result = OrderService::createOrder([
                'payId' => 'round-robin-' . $index,
                'type' => PayOrder::TYPE_WECHAT,
                'price' => '41.00',
                'param' => 'round-robin',
                'notifyUrl' => 'https://merchant.example/notify/rr-' . $index,
                'returnUrl' => 'https://merchant.example/return/rr-' . $index,
            ]);
            $channelIds[] = $result['channelId'];
        }

        $this->assertSame([1, 3, 4, 1, 3], $channelIds);
    }

    public function test_round_robin_falls_back_to_next_terminal_when_first_channel_price_slots_are_full(): void
    {
        $this->seedSettings(['allocationStrategy' => 'round_robin', 'payQf' => '1']);
        $this->createWechatTerminalWithChannel(2, 3, 'capacity-terminal', '容量回退终端', 20, 'weixin://pay/capacity-fallback');

        for ($cents = 5200; $cents <= 5209; $cents++) {
            TmpPrice::create([
                'oid' => 'occupied-' . $cents,
                'channel_id' => 1,
                'price' => number_format($cents / 100, 2, '.', ''),
            ]);
        }

        $result = OrderService::createOrder([
            'payId' => 'capacity-fallback-001',
            'type' => PayOrder::TYPE_WECHAT,
            'price' => '52.00',
            'param' => 'capacity-fallback',
            'notifyUrl' => 'https://merchant.example/notify/capacity',
            'returnUrl' => 'https://merchant.example/return/capacity',
        ]);

        $this->assertSame(2, $result['terminalId']);
        $this->assertSame(3, $result['channelId']);
        $this->assertSame('52.00', $result['reallyPrice']);
        $this->assertSame('weixin://pay/capacity-fallback', $result['payUrl']);
    }

    private function createWechatTerminalWithChannel(
        int $terminalId,
        int $channelId,
        string $terminalCode,
        string $terminalName,
        int $dispatchPriority,
        string $payUrl
    ): void {
        MonitorTerminal::create([
            'id' => $terminalId,
            'terminal_code' => $terminalCode,
            'terminal_name' => $terminalName,
            'dispatch_priority' => $dispatchPriority,
            'status' => 'enabled',
            'online_state' => 'online',
            'monitor_key' => $terminalCode . '-key',
            'last_heartbeat_at' => time(),
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.' . $terminalId,
            'device_meta' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        TerminalChannel::create([
            'id' => $channelId,
            'terminal_id' => $terminalId,
            'type' => PayOrder::TYPE_WECHAT,
            'channel_name' => $terminalName . '微信通道',
            'status' => 'enabled',
            'pay_url' => $payUrl,
            'last_used_at' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }
}
