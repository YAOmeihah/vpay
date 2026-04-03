<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\PayOrder;
use app\model\Setting;
use app\model\PayQrcode;
use app\model\TmpPrice;
use think\facade\Session;
use think\facade\Db;
use think\App;

class Admin extends BaseController
{
    public function index()
    {
        return 'Admin Controller - ThinkPHP 8';
    }

    public function getReturn($code = 1, $msg = "成功", $data = null)
    {
        return array("code" => $code, "msg" => $msg, "data" => $data);
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

        // IP检查可选（避免网络环境变化导致的问题）
        // $loginIp = Session::get("login_ip");
        // if ($loginIp && $loginIp !== $this->request->ip()) {
        //     Session::clear();
        //     return false;
        // }

        return true;
    }

    /**
     * 获取后台首页统计数据（带缓存）
     */
    public function getMain()
    {
        if (!$this->checkAdminAuth()) {
            return json($this->getReturn(-1, "没有登录"));
        }

        // 先从缓存获取统计数据
        $statsData = \app\service\CacheService::getStats('dashboard');
        if ($statsData) {
            return json($this->getReturn(1, "成功", $statsData));
        }

        $today = strtotime(date("Y-m-d"), time());

        // 今日总订单
        $todayOrder = PayOrder::where("create_date", ">=", $today)
            ->where("create_date", "<=", ($today + 86400))
            ->count();

        // 今日成功订单
        $todaySuccessOrder = PayOrder::where("state", ">=", 1)
            ->where("create_date", ">=", $today)
            ->where("create_date", "<=", ($today + 86400))
            ->count();

        // 今日失败订单
        $todayCloseOrder = PayOrder::where("state", -1)
            ->where("create_date", ">=", $today)
            ->where("create_date", "<=", ($today + 86400))
            ->count();

        // 今日收入
        $todayMoney = PayOrder::where("state", ">=", 1)
            ->where("create_date", ">=", $today)
            ->where("create_date", "<=", ($today + 86400))
            ->sum("price");

        // 总订单数
        $countOrder = PayOrder::count();
        
        // 总收入
        $countMoney = PayOrder::where("state", ">=", 1)->sum("price");

        // 获取MySQL版本
        $v = Db::query("SELECT VERSION()");
        $v = $v[0]['VERSION()'];

        // 获取GD库信息
        if (function_exists("gd_info")) {
            $gd_info = @gd_info();
            $gd = $gd_info["GD Version"];
        } else {
            $gd = '<font color="red">GD库未开启！</font>';
        }

        $statsData = array(
            "todayOrder" => $todayOrder,
            "todaySuccessOrder" => $todaySuccessOrder,
            "todayCloseOrder" => $todayCloseOrder,
            "todayMoney" => round((float)$todayMoney, 2),
            "countOrder" => $countOrder,
            "countMoney" => round((float)$countMoney),

            "PHP_VERSION" => PHP_VERSION,
            "PHP_OS" => PHP_OS,
            "SERVER" => $_SERVER['SERVER_SOFTWARE'],
            "MySql" => $v,
            "Thinkphp" => "v" . App::VERSION,
            "RunTime" => $this->sys_uptime(),
            "ver" => "v" . config('app.ver'), // 版本号
            "gd" => $gd,
        );

        // 缓存统计数据（5分钟）
        \app\service\CacheService::cacheStats('dashboard', $statsData);

        return json($this->getReturn(1, "成功", $statsData));
    }

    /**
     * 获取系统运行时间
     */
    private function sys_uptime()
    {
        $output = '';

        // Linux/Unix系统
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $str = @file("/proc/uptime");
            if ($str !== false) {
                $str = explode(" ", implode("", $str));
                $str = trim($str[0]);
                $min = $str / 60;
                $hours = $min / 60;
                $days = floor($hours / 24);
                $hours = floor($hours - ($days * 24));
                $min = floor($min - ($days * 60 * 24) - ($hours * 60));
                if ($days !== 0) $output .= $days . "天";
                if ($hours !== 0) $output .= $hours . "小时";
                if ($min !== 0) $output .= $min . "分钟";
                return $output;
            }
        }

        // Windows系统或其他系统
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows下获取系统启动时间
            $uptime = shell_exec('wmic os get lastbootuptime /value 2>nul');
            if ($uptime) {
                preg_match('/LastBootUpTime=(\d{14})/', $uptime, $matches);
                if (isset($matches[1])) {
                    $bootTime = \DateTime::createFromFormat('YmdHis', $matches[1]);
                    if ($bootTime) {
                        $now = new \DateTime();
                        $diff = $now->diff($bootTime);
                        if ($diff->days > 0) $output .= $diff->days . "天";
                        if ($diff->h > 0) $output .= $diff->h . "小时";
                        if ($diff->i > 0) $output .= $diff->i . "分钟";
                        return $output ?: "刚启动";
                    }
                }
            }
        }

        return "无法获取";
    }

    /**
     * 检查程序更新
     * Hrlni二改关闭更新
     */
    public function checkUpdate()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        // 直接返回最新版本，不进行网络请求
        return json($this->getReturn(0, "程序是最新版"));

        // 原始更新检查代码（已禁用）
        /*
        try {
            // 尝试获取最新版本信息
            $ver = $this->getCurl("https://raw.githubusercontent.com/szvone/vmqphp/master/ver");
            $ver = explode("|", $ver);

            if (count($ver) == 2 && $ver[0] != config('app.ver')) {
                return json($this->getReturn(1, "[v" . $ver[0] . "已于" . $ver[1] . "发布]", "https://github.com/szvone/vmqphp"));
            } else {
                return json($this->getReturn(0, "程序是最新版"));
            }
        } catch (\Exception $e) {
            // 如果网络请求失败，返回当前版本信息
            return json($this->getReturn(0, "程序是最新版"));
        }
        */
    }

    /**
     * 获取系统设置
     */
    public function getSettings()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $settings = [
            'user' => Setting::getConfigValue('user'),
            'pass' => '', // 密码字段不显示实际值，显示为空
            'notifyUrl' => Setting::getConfigValue('notifyUrl'),
            'returnUrl' => Setting::getConfigValue('returnUrl'),
            'key' => Setting::getConfigValue('key'),
            'lastheart' => Setting::getConfigValue('lastheart'),
            'lastpay' => Setting::getConfigValue('lastpay'),
            'jkstate' => Setting::getConfigValue('jkstate'),
            'close' => Setting::getConfigValue('close'),
            'payQf' => Setting::getConfigValue('payQf'),
            'wxpay' => Setting::getConfigValue('wxpay'),
            'zfbpay' => Setting::getConfigValue('zfbpay'),
            'epay_enabled' => Setting::getConfigValue('epay_enabled', '0'),
            'epay_pid' => Setting::getConfigValue('epay_pid'),
            'epay_key' => '',
            'epay_name' => Setting::getConfigValue('epay_name', '订单支付'),
            'epay_private_key' => '',
            'epay_public_key' => Setting::getConfigValue('epay_public_key'),
        ];

        // 如果key为空，生成一个新的
        if (empty($settings['key'])) {
            $settings['key'] = md5((string)time());
            Setting::setConfigValue('key', $settings['key']);
        }

        return json($this->getReturn(1, "成功", $settings));
    }

    /**
     * 保存系统设置
     */
    public function saveSetting()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $params = [
            'user', 'pass', 'notifyUrl', 'returnUrl', 'key',
            'close', 'payQf', 'wxpay', 'zfbpay',
            'epay_enabled', 'epay_pid', 'epay_key', 'epay_name',
            'epay_private_key', 'epay_public_key'
        ];

        foreach ($params as $param) {
            $value = $this->request->param($param, '');

            // 密码字段特殊处理
            if ($param === 'pass') {
                // 如果密码为空，跳过不保存（保持原密码不变）
                if (empty($value)) {
                    continue;
                }
                // 对新密码进行哈希处理
                $value = password_hash($value, PASSWORD_DEFAULT);
            }

            if (in_array($param, ['epay_key', 'epay_private_key', 'epay_public_key'], true)) {
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
            }

            if ($param === 'epay_enabled') {
                $value = $value === '1' ? '1' : '0';
            }

            if (in_array($param, ['epay_pid', 'epay_name'], true)) {
                $value = trim((string)$value);
            }

            Setting::setConfigValue($param, (string)$value);
        }

        // 清除统计缓存（配置变更可能影响统计）
        \app\service\CacheService::deleteStats('dashboard');

        return json($this->getReturn());
    }

    /**
     * 生成 RSA 密钥对
     */
    public function generateRsaKeys()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        if ($res === false) {
            return json($this->getReturn(-1, "RSA密钥生成失败"));
        }

        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);
        $publicKey = $details['key'] ?? '';

        if ($privateKey === '' || $publicKey === '') {
            return json($this->getReturn(-1, "RSA密钥导出失败"));
        }

        return json($this->getReturn(1, "成功", [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ]));
    }

    /**
     * 添加支付二维码
     */
    public function addPayQrcode()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        PayQrcode::create([
            "type" => (int)$this->request->param("type"),
            "pay_url" => $this->request->param("pay_url"),
            "price" => (float)$this->request->param("price"),
        ]);

        return json($this->getReturn());
    }

    /**
     * 获取支付二维码列表
     */
    public function getPayQrcodes()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $page = (int)$this->request->param("page", 1);
        $size = (int)$this->request->param("limit", 10);
        $type = $this->request->param("type");

        $query = PayQrcode::where("type", (int)$type);
        $count = $query->count();
        $array = $query->order("id", "desc")
            ->page($page, $size)
            ->select()
            ->toArray();

        return json([
            "code" => 0,
            "msg" => "获取成功",
            "data" => $array,
            "count" => $count
        ]);
    }

    /**
     * 删除支付二维码
     */
    public function delPayQrcode()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        PayQrcode::where("id", (int)$this->request->param("id"))->delete();
        return json($this->getReturn());
    }

    /**
     * 获取订单列表
     */
    public function getOrders()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $page = (int)$this->request->param("page", 1);
        $size = (int)$this->request->param("limit", 10);
        $type = $this->request->param("type");
        $state = $this->request->param("state");

        $query = PayOrder::order("id", "desc");

        if ($type) {
            $query = $query->where("type", (int)$type);
        }
        if ($state !== null && $state !== '') {
            $query = $query->where("state", (int)$state);
        }

        $count = $query->count();
        $array = $query->page($page, $size)->select()->toArray();

        return json([
            "code" => 0,
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
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $id = (int)$this->request->param("id");
        $res = PayOrder::where("id", $id)->find();

        PayOrder::where("id", $id)->delete();
        
        if ($res && $res['state'] == 0) {
            TmpPrice::where("oid", $res['order_id'])->delete();
        }

        return json($this->getReturn());
    }

    /**
     * 补单功能
     */
    public function setBd()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $id = (int)$this->request->param("id");
        $res = PayOrder::where("id", $id)->find();

        if ($res) {
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
                if ($res['state'] == 0) {
                    TmpPrice::where("oid", $res['order_id'])->delete();
                }

                PayOrder::where("id", $res['id'])->update(array("state" => 1));
                return json($this->getReturn());
            } else {
                return json($this->getReturn(-2, "补单失败"));
            }
        } else {
            return json($this->getReturn(-1, "订单不存在"));
        }
    }

    /**
     * 删除过期订单
     */
    public function delGqOrder()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        PayOrder::where("state", "-1")->delete();
        return json($this->getReturn());
    }

    /**
     * 删除一周前的订单
     */
    public function delLastOrder()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        PayOrder::where("create_date", "<", (time() - 604800))->delete();
        return json($this->getReturn());
    }

    /**
     * 生成二维码
     */
    public function enQrcode()
    {
        $url = $this->request->param('url', '');
        if (empty($url)) {
            return json($this->getReturn(-1, "URL参数不能为空"));
        }

        try {
            // 使用endroid/qr-code v6.0+ API
            $qrCode = new \Endroid\QrCode\QrCode(
                data: $url,
                encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
                errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Low,
                size: 200,
                margin: 10,
                roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin,
                foregroundColor: new \Endroid\QrCode\Color\Color(0, 0, 0),
                backgroundColor: new \Endroid\QrCode\Color\Color(255, 255, 255)
            );

            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);

            return response($result->getString(), 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => strlen($result->getString())
            ]);
        } catch (\Exception $e) {
            return json($this->getReturn(-1, "二维码生成失败: " . $e->getMessage()));
        }
    }

    /**
     * 发送HTTP请求
     */
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
