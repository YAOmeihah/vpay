<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\payment\PaymentTestLabService;

class PaymentTest extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function createPaymentTestOrder()
    {
        try {
            return $this->success($this->paymentTestLabService()->createOrder(
                (array)$this->request->param(),
                $this->requestBaseUrl()
            ));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getPaymentTestOrder()
    {
        try {
            return $this->success($this->paymentTestLabService()->getOrderStatus(
                (string)$this->request->param('orderId', '')
            ));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getPaymentTestCallback()
    {
        return $this->success($this->paymentTestLabService()->getLatestCallback(
            (string)$this->request->param('orderId', ''),
            (string)$this->request->param('payId', '')
        ));
    }

    private function requestBaseUrl(): string
    {
        return $this->request->scheme() . '://' . $this->request->host(true);
    }

    private function paymentTestLabService(): PaymentTestLabService
    {
        return $this->app->make(PaymentTestLabService::class);
    }
}
