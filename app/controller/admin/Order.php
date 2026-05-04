<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\MonitorTerminal;
use app\model\PayOrder;
use app\model\TmpPrice;
use app\service\NotifyService;
use app\service\admin\DashboardStatsService;
use app\service\order\OrderStateManager;
use think\facade\Db;

class Order extends BaseController
{
    use \app\controller\trait\ApiResponse;

    /**
     * 获取订单列表
     */
    public function getOrders()
    {
        $page = (int)$this->request->param("page", 1);
        $size = (int)$this->request->param("limit", 10);
        $type = $this->request->param("type");
        $state = $this->request->param("state");

        $query = Db::name('pay_order')->order("id", "desc");

        if ($type) {
            $query = $query->where("type", (int)$type);
        }
        if ($state !== null && $state !== '') {
            $query = $query->where("state", (int)$state);
        }

        $count = $query->count();
        $array = $query->page($page, $size)->select()->toArray();
        $terminalIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['terminal_id'] ?? 0),
            $array
        ))));
        $terminalCodes = [];
        if ($terminalIds !== []) {
            $terminalCodes = MonitorTerminal::whereIn('id', $terminalIds)->column('terminal_code', 'id');
        }

        $array = array_map(static function (array $row) use ($terminalCodes): array {
            $terminalId = (int) ($row['terminal_id'] ?? 0);
            $row['terminal_code'] = $terminalId > 0 ? (string) ($terminalCodes[$terminalId] ?? '') : '';

            return $row;
        }, $array);

        return json([
            "code" => 1,
            "msg" => "获取成功",
            "data" => $array,
            "count" => $count
        ]);
    }

    /**
     * 删除订单
     */
    public function delOrder()
    {
        $id = (int)$this->request->param("id");
        $res = PayOrder::where("id", $id)->find();

        PayOrder::where("id", $id)->delete();

        if ($res && $res['state'] == 0) {
            TmpPrice::where("oid", $res['order_id'])->delete();
        }

        if ($res) {
            $this->orderStateManager()->invalidateOrderView((string) $res['order_id']);
        } else {
            $this->dashboardStatsService()->clearStats();
        }

        return $this->success();
    }

    /**
     * 补单功能
     */
    public function setBd()
    {
        $id = (int)$this->request->param("id");
        $res = PayOrder::where("id", $id)->find();

        if ($res) {
            $orderData = $res->toArray();

            $notifyResult = NotifyService::sendNotifyDetailed($orderData);
            $notifyOk = $notifyResult['ok'];

            if ($notifyOk) {
                if ($res['state'] == 0) {
                    TmpPrice::where("oid", $res['order_id'])->delete();
                }

                PayOrder::where("id", $res['id'])->update(array("state" => 1));
                $this->orderStateManager()->invalidateOrderView((string) $res['order_id']);
                return $this->success();
            } else {
                $detail = trim((string)($notifyResult['detail'] ?? ''));
                return $this->respond(-2, "补单失败，异步通知返回错误", $detail !== '' ? $detail : null);
            }
        } else {
            return $this->error("订单不存在");
        }
    }

    /**
     * 删除过期订单
     */
    public function delGqOrder()
    {
        $orderIds = PayOrder::where("state", "-1")->column('order_id');
        PayOrder::where("state", "-1")->delete();
        $this->orderStateManager()->invalidateOrderViews($orderIds);
        return $this->success();
    }

    /**
     * 删除一周前的订单
     */
    public function delLastOrder()
    {
        $orderIds = PayOrder::where("create_date", "<", (time() - 604800))->column('order_id');
        PayOrder::where("create_date", "<", (time() - 604800))->delete();
        $this->orderStateManager()->invalidateOrderViews($orderIds);
        return $this->success();
    }

    private function orderStateManager(): OrderStateManager
    {
        return $this->app->make(OrderStateManager::class);
    }

    private function dashboardStatsService(): DashboardStatsService
    {
        return $this->app->make(DashboardStatsService::class);
    }
}
