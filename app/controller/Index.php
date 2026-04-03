<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\PayOrder;
use app\model\Setting;
use app\model\PayQrcode;
use app\model\TmpPrice;
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

    /**
     * 检查管理员权限
     */
    protected function checkAdminAuth()
    {
        if (!Session::has("admin")) {
            return false;
        }

        // 检查Session超时 (24小时，更宽松)
        $loginTime = Session::get("login_time");
        if ($loginTime && (time() - $loginTime) > 86400) {
            Session::clear();
            return false;
        }

        // 暂时禁用IP检查，避免网络环境变化导致的问题
        // $loginIp = Session::get("login_ip");
        // if ($loginIp && $loginIp !== $this->request->ip()) {
        //     Session::clear();
        //     return false;
        // }

        return true;
    }

    //后台用户登录
    public function login()
    {
        
        $clientIp = $this->request->ip();
        $loginKey = 'login_attempts_' . md5($clientIp);
        $attempts = cache($loginKey) ?: 0;

        if ($attempts >= 5) {
            return json($this->getReturn(-1, "登录失败次数过多，请5分钟后重试"));
        }
    

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

        // 限制登录频率
        $clientIp = $this->request->ip();
        $loginKey = 'login_attempts_' . md5($clientIp);
        $attempts = cache($loginKey) ?: 0;

        if ($attempts >= 5) {
            return json($this->getReturn(-1, "登录失败次数过多，请5分钟后重试"));
        }

        $_user = Setting::getConfigValue("user");
        $_pass = Setting::getConfigValue("pass");

        // 验证用户名和密码
        if (!hash_equals((string)$_user, $user) || !password_verify($pass, $_pass)) {
            // 记录失败次数
            cache($loginKey, $attempts + 1, 300); // 5分钟
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        // 登录成功，清除失败记录
        cache($loginKey, null);

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
        if (!$this->checkAdminAuth()) {
            return json($this->getReturn(-1, "没有登录"));
        }

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

        // 输入验证和过滤
        $payId = $this->request->param("payId");
        if (!$payId || $payId == "") {
            return json($this->getReturn(-1, "请传入商户订单号"));
        }
        // 基本安全检查，保持兼容性
        if (strlen($payId) > 100) {
            return json($this->getReturn(-1, "商户订单号长度超限"));
        }

        $type = (int)$this->request->param("type");
        if (!in_array($type, [1, 2])) {
            return json($this->getReturn(-1, "支付方式错误=>1|微信 2|支付宝"));
        }

        $price = $this->request->param("price");
        if (!$price || $price == "") {
            return json($this->getReturn(-1, "请传入订单金额"));
        }
        if ($price <= 0) {
            return json($this->getReturn(-1, "订单金额必须大于0"));
        }
        // 基本安全检查：防止过大金额
        if ($price > 999999.99) {
            return json($this->getReturn(-1, "订单金额超出限制"));
        }

        $sign = $this->request->param("sign");
        if (!$sign || $sign == "") {
            return json($this->getReturn(-1, "请传入签名"));
        }

        $isHtml = $this->request->param("isHtml");
        if (!$isHtml || $isHtml == "") {
            $isHtml = 0;
        }
        $param = $this->request->param("param");
        if (!$param) {
            $param = "";
        }

        $key = Setting::getConfigValue("key");

        if ($this->request->param("notifyUrl")) {
            $notify_url = $this->request->param("notifyUrl");
            // 基本安全检查：防止过长URL
            if (strlen($notify_url) > 1000) {
                return json($this->getReturn(-1, "回调地址长度超限"));
            }
        } else {
            $notify_url = Setting::getConfigValue("notifyUrl");
        }

        if ($this->request->param("returnUrl")) {
            $return_url = $this->request->param("returnUrl");
            // 基本安全检查：防止过长URL
            if (strlen($return_url) > 1000) {
                return json($this->getReturn(-1, "返回地址长度超限"));
            }
        } else {
            $return_url = Setting::getConfigValue("returnUrl");
        }

        $_sign = md5($payId . $param . $type . $price . $key);
        if ($sign != $_sign) {
            return json($this->getReturn(-1, "签名错误"));
        }

        $jkstate = Setting::getConfigValue("jkstate");
        if ($jkstate != "1") {
            // 根据isHtml参数返回不同格式的响应
            if ($isHtml == 1) {
                // 返回简约的HTML错误页面
                $html = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付异常</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 40px 30px;
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .error-icon {
            font-size: 48px;
            color: #ff6b6b;
            margin-bottom: 20px;
        }
        .error-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .error-message {
            font-size: 16px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 30px;
        }
        .retry-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .retry-btn:hover {
            background: #5a6fd8;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">支付异常</h1>
        <p class="error-message">我们正在修复支付系统，请稍后再试</p>
        <button class="retry-btn" onclick="history.back()">返回上页</button>
    </div>
</body>
</html>';
                return response($html)->header(['Content-Type' => 'text/html; charset=utf-8']);
            } else {
                // API调用返回JSON格式
                return json($this->getReturn(-1, "监控端状态异常，请检查"));
            }
        }

        $reallyPrice = bcmul((string)$price, '100');

        $payQf = Setting::getConfigValue("payQf");

        $orderId = date("YmdHms") . rand(1, 9) . rand(1, 9) . rand(1, 9) . rand(1, 9);

        $ok = false;
        for ($i = 0; $i < 10; $i++) {
            $tmpPrice = $reallyPrice . "-" . $type;

            try {
                TmpPrice::create(['price' => $tmpPrice, 'oid' => $orderId]);
                $ok = true;
                break;
            } catch (\Exception $e) {
                // 继续尝试
            }
            if ($payQf == 1) {
                $reallyPrice++;
            } else if ($payQf == 2) {
                $reallyPrice--;
            }
        }

        if (!$ok) {
            return json($this->getReturn(-1, "订单超出负荷，请稍后重试"));
        }

        $reallyPrice = bcdiv((string)$reallyPrice, '100', 2);

        if ($type == 1) {
            $payUrl = Setting::getConfigValue("wxpay");
        } else if ($type == 2) {
            $payUrl = Setting::getConfigValue("zfbpay");
        }

        if ($payUrl == "") {
            return json($this->getReturn(-1, "请您先进入后台配置程序"));
        }
        $isAuto = 1;
        $_payUrl = PayQrcode::where("price", $reallyPrice)
            ->where("type", $type)
            ->find();
        if ($_payUrl) {
            $payUrl = $_payUrl['pay_url'];
            $isAuto = 0;
        }

        $res = PayOrder::where("pay_id", $payId)->find();
        if ($res) {
            return json($this->getReturn(-1, "商户订单号已存在"));
        }

        $createDate = time();
        $data = array(
            "close_date" => 0,
            "create_date" => $createDate,
            "is_auto" => $isAuto,
            "notify_url" => $notify_url,
            "order_id" => $orderId,
            "param" => $param,
            "pay_date" => 0,
            "pay_id" => $payId,
            "pay_url" => $payUrl,
            "price" => $price,
            "really_price" => $reallyPrice,
            "return_url" => $return_url,
            "state" => 0,
            "type" => $type
        );

        PayOrder::create($data);

        if ($isHtml == 1) {
            echo "<script>window.location.href = 'payPage/pay.html?orderId=" . $orderId . "'</script>";
        } else {
            $time = Setting::getConfigValue("close");
            $data = array(
                "payId" => $payId,
                "orderId" => $orderId,
                "payType" => $type,
                "price" => $price,
                "reallyPrice" => $reallyPrice,
                "payUrl" => $payUrl,
                "isAuto" => $isAuto,
                "state" => 0,
                "timeOut" => $time,
                "date" => $createDate
            );

            // 缓存新创建的订单
            \app\service\CacheService::cacheOrder($orderId, $data);

            return json($this->getReturn(1, "成功", $data));
        }
    }

    //获取订单信息（带缓存）
    public function getOrder()
    {
        $orderId = $this->request->param("orderId");

        // 先从缓存获取
        $cachedData = \app\service\CacheService::getOrder($orderId);
        if ($cachedData) {
            return json($this->getReturn(1, "成功", $cachedData));
        }

        // 缓存未命中，从数据库获取
        $res = PayOrder::where("order_id", $orderId)->find();
        if ($res) {
            $time = Setting::getConfigValue("close");

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

            // 存入缓存
            \app\service\CacheService::cacheOrder($orderId, $data);

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

            $orderData = $res->toArray();

            if (\app\service\epay\EpayNotifyService::isEpayOrder($orderData)) {
                $epayConfig = \app\service\epay\EpayConfigService::getConfig();
                $signingKey = \app\service\epay\EpayNotifyService::isEpayV2Order($orderData)
                    ? $epayConfig['private_key']
                    : $epayConfig['key'];
                $url = \app\service\epay\EpayNotifyService::buildReturnUrl($orderData, $signingKey);
            } else {
                $key = Setting::getConfigValue("key");

                $res['price'] = number_format((float)$res['price'], 2, ".", "");
                $res['really_price'] = number_format((float)$res['really_price'], 2, ".", "");

                $p = "payId=" . $res['pay_id'] . "&param=" . $res['param'] . "&type=" . $res['type'] . "&price=" . $res['price'] . "&reallyPrice=" . $res['really_price'];

                $sign = $res['pay_id'] . $res['param'] . $res['type'] . $res['price'] . $res['really_price'] . $key;
                $p = $p . "&sign=" . md5($sign);

                $url = $res['return_url'];

                if (strpos($url, "?") === false) {
                    $url = $url . "?" . $p;
                } else {
                    $url = $url . "&" . $p;
                }
            }

            return json($this->getReturn(1, "成功", $url));
        } else {
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }

    //关闭订单
    public function closeOrder()
    {
        $key = Setting::getConfigValue("key");
        $orderId = $this->request->param("orderId");

        $_sign = $orderId . $key;

        if (md5($_sign) != $this->request->param("sign")) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $res = PayOrder::where("order_id", $orderId)->find();

        if ($res) {
            if ($res['state'] != 0) {
                return json($this->getReturn(-1, "订单状态不允许关闭"));
            }
            PayOrder::where("order_id", $orderId)->update(array("state" => -1, "close_date" => time()));
            TmpPrice::where("oid", $res['order_id'])->delete();

            // 清除订单缓存
            \app\service\CacheService::deleteOrder($orderId);
            // 清除统计缓存（因为订单状态变化会影响统计）
            \app\service\CacheService::deleteStats('dashboard');

            return json($this->getReturn(1, "成功"));
        } else {
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }

    //获取监控端状态
    public function getState()
    {
        $key = Setting::getConfigValue("key");
        $t = $this->request->param("t");

        $_sign = $t . $key;

        if (md5($_sign) != $this->request->param("sign")) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $lastheart = Setting::getConfigValue("lastheart");
        $lastpay = Setting::getConfigValue("lastpay");
        $jkstate = Setting::getConfigValue("jkstate");

        return json($this->getReturn(1, "成功", array("lastheart" => $lastheart, "lastpay" => $lastpay, "jkstate" => $jkstate)));
    }

    //App心跳接口
    public function appHeart()
    {
        $this->closeEndOrder();

        $key = Setting::getConfigValue("key");
        $t = $this->request->param("t");

        $_sign = $t . $key;

        if (md5($_sign) != $this->request->param("sign")) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        Setting::setConfigValue("lastheart", (string)time());
        Setting::setConfigValue("jkstate", "1");
        return json($this->getReturn());
    }

    //App推送付款数据接口
    public function appPush()
    {
        $this->closeEndOrder();

        $key = Setting::getConfigValue("key");
        $t = $this->request->param("t");
        $type = $this->request->param("type");
        $price = $this->request->param("price");

        $_sign = $type . $price . $t . $key;

        if (md5($_sign) != $this->request->param("sign")) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        Setting::setConfigValue("lastpay", (string)time());

        $res = PayOrder::where("really_price", $price)
            ->where("state", 0)
            ->where("type", $type)
            ->find();

        if ($res) {
            $affected = PayOrder::where("id", $res['id'])->where("state", 0)
                ->update(array("state" => 1, "pay_date" => time(), "close_date" => time()));

            if ($affected === 0) {
                return json($this->getReturn(1, "订单已处理"));
            }

            TmpPrice::where("oid", $res['order_id'])->delete();

            $orderData = $res->toArray();

            if (\app\service\epay\EpayNotifyService::isEpayOrder($orderData)) {
                $epayConfig = \app\service\epay\EpayConfigService::getConfig();
                $signingKey = \app\service\epay\EpayNotifyService::isEpayV2Order($orderData)
                    ? $epayConfig['private_key']
                    : $epayConfig['key'];
                $notifyOk = \app\service\epay\EpayNotifyService::sendNotify($orderData, $signingKey);
            } else {
                $url = $res['notify_url'];

                $key = Setting::getConfigValue("key");

                $p = "payId=" . $res['pay_id'] . "&param=" . $res['param'] . "&type=" . $res['type'] . "&price=" . $res['price'] . "&reallyPrice=" . $res['really_price'];

                $sign = $res['pay_id'] . $res['param'] . $res['type'] . $res['price'] . $res['really_price'] . $key;
                $p = $p . "&sign=" . md5($sign);

                if (strpos($url, "?") === false) {
                    $url = $url . "?" . $p;
                } else {
                    $url = $url . "&" . $p;
                }

                $notifyOk = $this->getCurl($url) == "success";
            }

            if ($notifyOk) {
                return json($this->getReturn());
            } else {
                PayOrder::where("id", $res['id'])->update(array("state" => 2));
                return json($this->getReturn(-1, "异步通知失败"));
            }
        } else {
            $data = array(
                "close_date" => 0,
                "create_date" => time(),
                "is_auto" => 0,
                "notify_url" => "",
                "order_id" => "无订单转账",
                "param" => "无订单转账",
                "pay_date" => 0,
                "pay_id" => "无订单转账",
                "pay_url" => "",
                "price" => $price,
                "really_price" => $price,
                "return_url" => "",
                "state" => 1,
                "type" => $type
            );

            PayOrder::create($data);
            return json($this->getReturn());
        }
    }

    //关闭过期订单接口(请用定时器至少1分钟调用一次)
    public function closeEndOrder()
    {
        $lastheart = Setting::getConfigValue("lastheart");
        if ((time() - intval($lastheart)) > 90) {
            Setting::setConfigValue("jkstate", "0");
        }

        $time = Setting::getConfigValue("close");

        $closeTime = time() - 60 * intval($time);
        $close_date = time();

        $res = PayOrder::where("create_date", "<=", $closeTime)
            ->where("state", 0)
            ->update(array("state" => -1, "close_date" => $close_date));

        if ($res) {
            $rows = PayOrder::where("close_date", $close_date)->select();
            foreach ($rows as $row) {
                TmpPrice::where("oid", $row['order_id'])->delete();
            }

            $rows = TmpPrice::select();
            foreach ($rows as $row) {
                $re = PayOrder::where("order_id", $row['oid'])->find();
                if (!$re) {
                    TmpPrice::where("oid", $row['oid'])->delete();
                }
            }

            return json($this->getReturn(1, "成功清理" . $res . "条订单"));
        } else {
            return json($this->getReturn(1, "没有等待清理的订单"));
        }
    }

    //发送Http请求
    protected function getCurl($url, $post = 0, $cookie = 0, $header = 0, $nobaody = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $klsf[] = 'Accept:*/*';
        $klsf[] = 'Accept-Language:zh-cn';
        $klsf[] = 'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 11_2_1 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C153 MicroMessenger/6.6.1 NetType/WIFI Language/zh_CN';
        $klsf[] = 'Referer:' . $url;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $klsf);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($nobaody) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }
}
