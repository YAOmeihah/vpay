<?php
declare(strict_types=1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\PayOrder;
use app\model\TmpPrice;
use app\service\MonitorService;
use app\service\NotifyService;
use app\service\SignService;
use app\service\config\SettingSystemConfig;

class Order extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function createOrder()
    {
        MonitorService::closeExpiredOrders();

        $params = $this->request->param();
        $validate = new \app\validate\OrderValidate();
        if (!$validate->check($params)) {
            return json($this->getReturn(-1, $validate->getError()));
        }

        $payId = $params['payId'];
        $type = (int)$params['type'];
        $price = $params['price'];
        $sign = $params['sign'];
        $isHtml = $params['isHtml'] ?? 0;
        $param = $params['param'] ?? '';

        if (!SignService::verifyCreateOrderSign($payId, $param, $type, $price, $sign)) {
            return json($this->getReturn(-1, "签名错误"));
        }

        try {
            $orderParams = [
                'payId'     => $payId,
                'type'      => $type,
                'price'     => $price,
                'param'     => $param,
                'notifyUrl' => $params['notifyUrl'] ?? null,
                'returnUrl' => $params['returnUrl'] ?? null,
            ];

            $orderInfo = \app\service\OrderService::createOrder($orderParams);

            if ($isHtml == 1) {
                echo "<script>window.location.href = 'payPage/pay.html?orderId=" . $orderInfo['orderId'] . "'</script>";
            } else {
                return json($this->getReturn(1, "成功", $orderInfo));
            }
        } catch (\RuntimeException $e) {
            if ($isHtml == 1) {
                return response($this->renderErrorHtml($e->getMessage()))
                    ->header(['Content-Type' => 'text/html; charset=utf-8']);
            }
            return json($this->getReturn(-1, $e->getMessage()));
        }
    }

    private function renderErrorHtml(string $msg): string
    {
        $msg = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付异常</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .error-card { background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); padding: 40px 30px; text-align: center; max-width: 400px; width: 100%; }
        .error-icon { font-size: 48px; color: #ff6b6b; margin-bottom: 20px; }
        .error-title { font-size: 24px; color: #333; margin-bottom: 15px; font-weight: 600; }
        .error-message { font-size: 16px; color: #666; line-height: 1.5; margin-bottom: 30px; }
        .retry-btn { background: #667eea; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 16px; cursor: pointer; transition: background 0.3s ease; }
        .retry-btn:hover { background: #5a6fd8; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">支付异常</h1>
        <p class="error-message">' . $msg . '</p>
        <button class="retry-btn" onclick="history.back()">返回上页</button>
    </div>
</body>
</html>';
    }

    public function getOrder()
    {
        $orderId = $this->request->param("orderId");

        $cachedData = \app\service\CacheService::getOrder($orderId);
        if ($cachedData) {
            return json($this->getReturn(1, "成功", $cachedData));
        }

        $res = PayOrder::where("order_id", $orderId)->find();
        if ($res) {
            $time = $this->systemConfig()->getOrderCloseRaw();

            $data = array(
                "payId" => $res['pay_id'],
                "orderId" => $res['order_id'],
                "payType" => $res['type'],
                "price" => $res['price'],
                "reallyPrice" => $res['really_price'],
                "payUrl" => $res['pay_url'],
                "isAuto" => $res['is_auto'],
                "state" => $res['state'],
                "timeOut" => $time,
                "date" => $res['create_date']
            );

            \app\service\CacheService::cacheOrder($orderId, $data);

            return json($this->getReturn(1, "成功", $data));
        } else {
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }

    public function checkOrder()
    {
        $res = PayOrder::where("order_id", $this->request->param("orderId"))->find();
        if ($res) {
            if ($res['state'] == 0) {
                return json($this->getReturn(-1, "订单未支付"));
            }
            if ($res['state'] == -1) {
                return json($this->getReturn(-1, "订单已过期"));
            }

            $url = NotifyService::buildReturnUrl($res->toArray());

            return json($this->getReturn(1, "成功", $url));
        } else {
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }

    public function closeOrder()
    {
        $orderId = $this->request->param("orderId");

        if (!SignService::verifySimpleSign($orderId, $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $res = PayOrder::where("order_id", $orderId)->find();

        if ($res) {
            if ($res['state'] != 0) {
                return json($this->getReturn(-1, "订单状态不允许关闭"));
            }
            PayOrder::where("order_id", $orderId)->update(array("state" => -1, "close_date" => time()));
            TmpPrice::where("oid", $res['order_id'])->delete();

            \app\service\CacheService::deleteOrder($orderId);
            \app\service\CacheService::deleteStats('dashboard');

            return json($this->getReturn(1, "成功"));
        } else {
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }

    private function systemConfig(): SettingSystemConfig
    {
        return $this->app->make(SettingSystemConfig::class);
    }
}
