<?php
declare(strict_types=1);

namespace tests;

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
            ['payId', 'orderId', 'payType', 'price', 'reallyPrice', 'payUrl', 'isAuto', 'state', 'timeOut', 'date'],
            array_keys($result)
        );
        $this->assertSame('native-order-001', $result['payId']);
        $this->assertSame(PayOrder::TYPE_WECHAT, $result['payType']);
        $this->assertSame('10.00', $result['price']);
        $this->assertSame('10.00', $result['reallyPrice']);
        $this->assertSame('weixin://matched-qrcode', $result['payUrl']);
        $this->assertSame(0, $result['isAuto']);

        $order = PayOrder::where('pay_id', 'native-order-001')->findOrFail();
        $this->assertSame('native-param', $order->getAttr('param'));
        $this->assertSame('weixin://matched-qrcode', $order->getAttr('pay_url'));

        $this->assertSame($result, CacheService::getOrder($result['orderId']));
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
}
