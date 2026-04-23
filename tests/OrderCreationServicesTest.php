<?php
declare(strict_types=1);

namespace tests;

use app\model\PaymentEvent;
use app\model\PayOrder;
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
        $first = OrderService::handlePayPush('66.66', PayOrder::TYPE_WECHAT);
        $second = OrderService::handlePayPush('77.77', PayOrder::TYPE_WECHAT);

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
}
