<?php
declare(strict_types=1);

namespace app\controller\monitor;

use app\BaseController;
use app\model\Setting;
use app\service\MonitorService;
use app\service\OrderService;
use app\service\SignService;

class Monitor extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function getState()
    {
        $t = $this->request->param("t");

        if (!SignService::verifySimpleSign($t, $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $lastheart = Setting::getConfigValue("lastheart");
        $lastpay = Setting::getConfigValue("lastpay");
        $jkstate = Setting::getConfigValue("jkstate");

        return json($this->getReturn(1, "成功", array("lastheart" => $lastheart, "lastpay" => $lastpay, "jkstate" => $jkstate)));
    }

    public function appHeart()
    {
        MonitorService::closeExpiredOrders();

        $t = $this->request->param("t");

        if (!SignService::verifySimpleSign($t, $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        MonitorService::heartbeat();
        return json($this->getReturn());
    }

    public function appPush()
    {
        MonitorService::closeExpiredOrders();

        $t = $this->request->param('t');
        $type = $this->request->param('type');
        $price = $this->request->param('price');

        if (!SignService::verifySimpleSign($type . $price . $t, $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $result = OrderService::handlePayPush($price, (int)$type);

        if ($result['alreadyProcessed']) {
            return json($this->getReturn(1, "订单已处理"));
        }

        if ($result['notifyOk']) {
            return json($this->getReturn());
        }

        return json($this->getReturn(-1, "异步通知失败"));
    }

    public function closeEndOrder()
    {
        $affected = MonitorService::closeExpiredOrders();

        if ($affected > 0) {
            return json($this->getReturn(1, "成功清理" . $affected . "条订单"));
        }

        return json($this->getReturn(1, "没有等待清理的订单"));
    }
}
