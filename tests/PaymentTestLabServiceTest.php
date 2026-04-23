<?php
declare(strict_types=1);

namespace tests;

use app\model\PayOrder;
use app\service\NotifyService;
use app\service\payment\PaymentTestLabService;

class PaymentTestLabServiceTest extends TestCase
{
    public function test_create_order_uses_internal_callback_defaults_and_returns_debug_payload(): void
    {
        $service = new PaymentTestLabService();

        $result = $service->createOrder([
            'type' => '1',
            'price' => '12.34',
            'payId' => 'lab-order-001',
            'param' => 'debug-payload',
        ], 'http://vpay.test');

        $this->assertSame('lab-order-001', $result['order']['payId']);
        $this->assertSame(1, $result['order']['payType']);
        $this->assertSame('12.34', (string) $result['order']['price']);
        $this->assertSame('weixin://default-pay-url', $result['order']['payUrl']);
        $this->assertSame(
            'http://vpay.test/payPage/pay.html?orderId=' . $result['order']['orderId'],
            $result['payPageUrl']
        );
        $this->assertSame('debug-payload', $result['request']['param']);
        $this->assertStringStartsWith('http://vpay.test/payment-test/notify', $result['request']['notifyUrl']);
        $this->assertStringStartsWith('http://vpay.test/payment-test/return', $result['request']['returnUrl']);
        $this->assertSame('默认终端', $result['assignment']['terminalName']);
        $this->assertSame('默认微信通道', $result['assignment']['channelName']);
    }

    public function test_notify_service_records_internal_payment_lab_callback_without_http_round_trip(): void
    {
        $service = new PaymentTestLabService();
        $created = $service->createOrder([
            'type' => '2',
            'price' => '8.88',
            'payId' => 'lab-order-notify',
            'param' => 'notify-check',
        ], 'http://vpay.test');

        $order = PayOrder::where('order_id', $created['order']['orderId'])->find();
        $this->assertNotNull($order);

        $notifyResult = NotifyService::sendNotifyDetailed($order->toArray());
        $callback = $service->getLatestCallback($created['order']['orderId'], 'lab-order-notify');

        $this->assertTrue($notifyResult['ok']);
        $this->assertSame('success', $notifyResult['response']);
        $this->assertSame('notify', $callback['kind']);
        $this->assertSame('lab-order-notify', $callback['payload']['payId']);
        $this->assertSame('8.88', $callback['payload']['price']);
        $this->assertSame($created['order']['orderId'], $callback['orderId']);
    }
}
