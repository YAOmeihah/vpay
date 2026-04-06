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
use app\service\order\OrderStateManager;
use think\facade\View;

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
        $meta = $this->resolveErrorPageMeta($msg);

        return View::fetch('/error', [
            'title' => $meta['title'],
            'message' => htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'),
            'helpText' => $meta['helpText'],
            'buttonText' => '返回上页',
        ]);
    }

    /**
     * @return array{title: string, helpText: string}
     */
    private function resolveErrorPageMeta(string $message): array
    {
        return match ($message) {
            '监控端状态异常，请检查' => [
                'title' => '监控端状态异常',
                'helpText' => '请确认监控端恢复在线后，再重新发起支付。',
            ],
            '商户订单号已存在' => [
                'title' => '商户订单重复',
                'helpText' => '请更换商户订单号后，再重新发起支付。',
            ],
            '订单超出负荷，请稍后重试' => [
                'title' => '当前下单繁忙',
                'helpText' => '系统正在处理较多订单，请稍后重试。',
            ],
            '请您先进入后台配置程序' => [
                'title' => '支付配置未完成',
                'helpText' => '请先在后台完成支付配置后，再重新发起支付。',
            ],
            '订单重复，请重试' => [
                'title' => '订单重复，请重试',
                'helpText' => '请返回商户页面刷新后，再重新发起支付。',
            ],
            default => [
                'title' => '支付异常',
                'helpText' => '请稍后重试，或返回商户页面重新发起支付。',
            ],
        };
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

            $this->orderStateManager()->invalidateOrderView($orderId);

            return json($this->getReturn(1, "成功"));
        } else {
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }

    private function systemConfig(): SettingSystemConfig
    {
        return $this->app->make(SettingSystemConfig::class);
    }

    private function orderStateManager(): OrderStateManager
    {
        return $this->app->make(OrderStateManager::class);
    }
}
