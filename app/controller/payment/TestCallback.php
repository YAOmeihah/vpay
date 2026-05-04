<?php
declare(strict_types=1);

namespace app\controller\payment;

use app\BaseController;
use app\service\payment\PaymentTestLabService;

class TestCallback extends BaseController
{
    public function notify()
    {
        try {
            $this->paymentTestLabService()->recordCallback(
                'notify',
                (array)$this->request->param(),
                (string)$this->request->ip()
            );
        } catch (\RuntimeException) {
            return response('forbidden', 403)->header(['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return response('success')->header(['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function returnUrl()
    {
        try {
            $callback = $this->paymentTestLabService()->recordCallback(
                'return',
                (array)$this->request->param(),
                (string)$this->request->ip()
            );
        } catch (\RuntimeException) {
            return response('forbidden', 403)->header(['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $payId = htmlspecialchars((string)$callback['payId'], ENT_QUOTES, 'UTF-8');
        $orderId = htmlspecialchars((string)$callback['orderId'], ENT_QUOTES, 'UTF-8');

        return response(
            '<!doctype html><html lang="zh-CN"><meta charset="utf-8"><title>VPay Payment Lab Return</title>'
            . '<body style="font-family:Arial,sans-serif;background:#0f172a;color:#f8fafc;padding:32px">'
            . '<h1>支付同步回跳已捕获</h1>'
            . '<p>Pay ID: ' . $payId . '</p>'
            . '<p>Order ID: ' . $orderId . '</p>'
            . '</body></html>'
        )->header(['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function paymentTestLabService(): PaymentTestLabService
    {
        return $this->app->make(PaymentTestLabService::class);
    }
}
