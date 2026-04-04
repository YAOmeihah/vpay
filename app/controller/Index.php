<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\PayOrder;
use app\model\TmpPrice;
use app\service\MonitorService;
use app\service\NotifyService;
use app\service\SignService;
use app\service\admin\AdminSettingsService;
use app\service\admin\DashboardStatsService;
use app\service\cache\OrderCache;
use app\service\config\SettingSystemConfig;
use app\service\runtime\SettingMonitorState;
use app\service\security\LoginAttemptLimiter;
use think\facade\Session;

class Index extends BaseController
{
    use \app\controller\trait\ApiResponse;
    public function index()
    {
        return 'ThinkPHP 8 支付系统已成功运行！';
    }

    public function test()
    {
        try {
            // 测试数据库连接
            $db = \think\facade\Db::connect();
            $result = $db->query('SELECT 1 as test');
            return json(['code' => 1, 'msg' => '数据库连接成功', 'data' => $result]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '数据库连接失败: ' . $e->getMessage()]);
        }
    }

    //后台用户登录
    public function login()
    {
        $user = trim($this->request->param("user"));
        $pass = trim($this->request->param("pass"));

        // 基本输入检查
        if (!$user || $user == "" || !$pass || $pass == "") {
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        // 基本安全检查：防止过长输入
        if (strlen($user) > 100 || strlen($pass) > 200) {
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        $clientIp = $this->request->ip();
        $limiter = $this->loginAttemptLimiter();

        if ($limiter->tooManyLoginAttempts($clientIp)) {
            return json($this->getReturn(-1, "登录失败次数过多，请5分钟后重试"));
        }

        $_user = $this->adminSettingsService()->getAdminUsername();
        $_pass = $this->adminSettingsService()->getAdminPasswordHash();

        if (!hash_equals((string)$_user, $user) || !password_verify($pass, $_pass)) {
            $limiter->recordLoginFailure($clientIp);
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        $limiter->clearLoginAttempts($clientIp);

        // 确保Session已启动
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 设置Session信息
        Session::set("admin", 1);
        Session::set("login_time", time());
        Session::set("login_ip", $clientIp);

        // 重新生成Session ID防止会话固定攻击（在设置Session后）
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(false); // 改为false，保留Session数据
        }

        return json($this->getReturn(1, "登录成功"));
    }

    //后台菜单
    public function getMenu()
    {
        $menu = array(
            array(
                "name" => "系统设置",
                "type" => "url",
                "url" => "admin/setting.html?t=" . time(),
            ),
            array(
                "name" => "监控端设置",
                "type" => "url",
                "url" => "admin/jk.html?t=" . time(),
            ),
            array(
                "name" => "微信二维码",
                "type" => "menu",
                "node" => array(
                    array(
                        "name" => "添加",
                        "type" => "url",
                        "url" => "admin/addwxqrcode.html?t=" . time(),
                    ),
                    array(
                        "name" => "管理",
                        "type" => "url",
                        "url" => "admin/wxqrcodelist.html?t=" . time(),
                    )
                ),
            ), array(
                "name" => "支付宝二维码",
                "type" => "menu",
                "node" => array(
                    array(
                        "name" => "添加",
                        "type" => "url",
                        "url" => "admin/addzfbqrcode.html?t=" . time(),
                    ),
                    array(
                        "name" => "管理",
                        "type" => "url",
                        "url" => "admin/zfbqrcodelist.html?t=" . time(),
                    )
                ),
            ), array(
                "name" => "订单列表",
                "type" => "url",
                "url" => "admin/orderlist.html?t=" . time(),
            ), array(
                "name" => "Api说明",
                "type" => "url",
                "url" => "api.html?t=" . time(),
            )
        );

        return json($menu);
    }

    //创建订单
    public function createOrder()
    {
        $this->closeEndOrder();

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

    //获取订单信息（带缓存）
    public function getOrder()
    {
        $orderId = $this->request->param("orderId");

        $cachedData = $this->orderCache()->getOrder($orderId);
        if ($cachedData) {
            return json($this->getReturn(1, "成功", $cachedData));
        }

        // 缓存未命中，从数据库获取
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

            $this->orderCache()->cacheOrder($orderId, $data);

            return json($this->getReturn(1, "成功", $data));
        } else {
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }

    //查询订单状态
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

    //关闭订单
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

            $this->orderCache()->deleteOrder($orderId);
            $this->dashboardStatsService()->clearStats();

            return json($this->getReturn(1, "成功"));
        } else {
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }

    //获取监控端状态
    public function getState()
    {
        $t = $this->request->param("t");

        if (!SignService::verifySimpleSign($t, $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $monitorState = $this->monitorState();
        $lastheart = $monitorState->getLastHeartbeatRaw();
        $lastpay = $monitorState->getLastPaidRaw();
        $jkstate = $monitorState->getOnlineFlagRaw();

        return json($this->getReturn(1, "成功", array("lastheart" => $lastheart, "lastpay" => $lastpay, "jkstate" => $jkstate)));
    }

    //App心跳接口
    public function appHeart()
    {
        $this->closeEndOrder();

        $t = $this->request->param("t");

        if (!SignService::verifySimpleSign($t, $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        MonitorService::heartbeat();
        return json($this->getReturn());
    }

    //App推送付款数据接口
    public function appPush()
    {
        $this->closeEndOrder();

        $t = $this->request->param('t');
        $type = $this->request->param('type');
        $price = $this->request->param('price');

        if (!SignService::verifySimpleSign($type . $price . $t, $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $result = \app\service\OrderService::handlePayPush($price, (int)$type);

        if ($result['alreadyProcessed']) {
            return json($this->getReturn(1, "订单已处理"));
        }

        if ($result['notifyOk']) {
            return json($this->getReturn());
        }

        return json($this->getReturn(-1, "异步通知失败"));
    }

    //关闭过期订单接口(请用定时器至少1分钟调用一次)
    public function closeEndOrder()
    {
        $affected = MonitorService::closeExpiredOrders();

        if ($affected > 0) {
            return json($this->getReturn(1, "成功清理" . $affected . "条订单"));
        }

        return json($this->getReturn(1, "没有等待清理的订单"));
    }

    private function adminSettingsService(): AdminSettingsService
    {
        return new AdminSettingsService();
    }

    private function dashboardStatsService(): DashboardStatsService
    {
        return new DashboardStatsService();
    }

    private function loginAttemptLimiter(): LoginAttemptLimiter
    {
        return new LoginAttemptLimiter();
    }

    private function orderCache(): OrderCache
    {
        return new OrderCache();
    }

    private function systemConfig(): SettingSystemConfig
    {
        return new SettingSystemConfig();
    }

    private function monitorState(): SettingMonitorState
    {
        return new SettingMonitorState();
    }

}
