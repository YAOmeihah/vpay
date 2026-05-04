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
        parse_str((string) parse_url($result['request']['notifyUrl'], PHP_URL_QUERY), $notifyQuery);
        parse_str((string) parse_url($result['request']['returnUrl'], PHP_URL_QUERY), $returnQuery);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) ($notifyQuery['token'] ?? ''));
        $this->assertSame($notifyQuery['token'], $returnQuery['token']);
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

    public function test_notify_service_reports_invalid_internal_callback_token_as_failure(): void
    {
        $service = new PaymentTestLabService();
        $created = $service->createOrder([
            'type' => '1',
            'price' => '6.66',
            'payId' => 'lab-order-invalid-token',
            'param' => 'notify-invalid-token',
        ], 'http://vpay.test');

        $order = PayOrder::where('order_id', $created['order']['orderId'])->findOrFail();
        $orderPayload = $order->toArray();
        $orderPayload['notify_url'] = 'http://vpay.test/payment-test/notify?vpayPaymentLab=1';

        $notifyResult = NotifyService::sendNotifyDetailed($orderPayload);

        $this->assertFalse($notifyResult['ok']);
        $this->assertSame('测试回调令牌无效', $notifyResult['detail']);
        $this->assertSame('', $notifyResult['response']);
    }

    public function test_payment_test_callback_requires_generated_token(): void
    {
        $service = new PaymentTestLabService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('测试回调令牌无效');

        $service->recordCallback('notify', [
            'payId' => 'lab-order-without-token',
            'orderId' => 'order-without-token',
            'vpayPaymentLab' => '1',
        ]);
    }
}
