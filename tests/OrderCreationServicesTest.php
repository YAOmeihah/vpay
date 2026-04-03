<?php
declare(strict_types=1);

namespace tests;

use app\model\PayOrder;
use app\service\CacheService;
use app\service\OrderService;
use app\service\epay\EpayOrderService;
use app\service\epay\EpaySignService;

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

    public function test_epay_v1_create_keeps_original_response_shape(): void
    {
        $params = [
            'pid' => '10001',
            'type' => 'wxpay',
            'out_trade_no' => 'epay-v1-001',
            'money' => '12.34',
            'notify_url' => 'https://merchant.example/notify/epay-v1',
            'return_url' => 'https://merchant.example/return/epay-v1',
            'param' => 'from-v1',
        ];
        $params['sign'] = EpaySignService::makeMd5($params, 'epay-md5-key');

        $result = EpayOrderService::create($params);

        $this->assertSame(
            ['trade_no', 'payurl', 'qrcode', 'urlscheme'],
            array_keys($result)
        );
        $this->assertNotSame('', $result['trade_no']);
        $this->assertSame('weixin://default-pay-url', $result['payurl']);
        $this->assertSame('weixin://default-pay-url', $result['qrcode']);
        $this->assertSame('', $result['urlscheme']);

        $order = PayOrder::where('pay_id', 'epay-v1-001')->findOrFail();
        $this->assertSame('epay:from-v1', $order->getAttr('param'));
        $this->assertSame((float) '12.34', (float) $order->getAttr('price'));
    }

    public function test_epay_v2_create_keeps_original_response_shape_and_pay_type_detection(): void
    {
        $params = [
            'pid' => '10001',
            'type' => 'wxpay',
            'out_trade_no' => 'epay-v2-001',
            'money' => '23.45',
            'notify_url' => 'https://merchant.example/notify/epay-v2',
            'return_url' => 'https://merchant.example/return/epay-v2',
            'timestamp' => time(),
            'param' => 'from-v2',
        ];
        $params['sign'] = EpaySignService::makeRsa($params, $this->getPrivateKeyPem());

        $result = EpayOrderService::createV2($params);

        $this->assertSame(
            ['trade_no', 'pay_type', 'pay_info'],
            array_keys($result)
        );
        $this->assertNotSame('', $result['trade_no']);
        $this->assertSame('qrcode', $result['pay_type']);
        $this->assertSame('weixin://default-pay-url', $result['pay_info']);

        $order = PayOrder::where('pay_id', 'epay-v2-001')->findOrFail();
        $this->assertSame('epayv2:from-v2', $order->getAttr('param'));
        $this->assertSame((float) '23.45', (float) $order->getAttr('price'));
    }
}
