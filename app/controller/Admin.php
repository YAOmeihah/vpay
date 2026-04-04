<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\PayOrder;
use app\model\PayQrcode;
use app\model\TmpPrice;
use app\service\NotifyService;
use app\service\admin\AdminPermissionService;
use app\service\admin\AdminSettingsService;
use app\service\admin\DashboardStatsService;
use think\facade\Db;
use think\facade\Session;
use think\App;

class Admin extends BaseController
{
    use \app\controller\trait\ApiResponse;
    public function index()
    {
        return 'Admin Controller - ThinkPHP 8';
    }

    /**
     * 获取后台首页统计数据（带缓存）
     */
    public function getMain()
    {
        $statsData = $this->dashboardStatsService()->getStats(function (): array {
            $today = strtotime(date("Y-m-d"), time());

            $todayOrder = PayOrder::where("create_date", ">=", $today)
                ->where("create_date", "<=", ($today + 86400))
                ->count();

            $todaySuccessOrder = PayOrder::where("state", ">=", 1)
                ->where("create_date", ">=", $today)
                ->where("create_date", "<=", ($today + 86400))
                ->count();

            $todayCloseOrder = PayOrder::where("state", -1)
                ->where("create_date", ">=", $today)
                ->where("create_date", "<=", ($today + 86400))
                ->count();

            $todayMoney = PayOrder::where("state", ">=", 1)
                ->where("create_date", ">=", $today)
                ->where("create_date", "<=", ($today + 86400))
                ->sum("price");

            $countOrder = PayOrder::count();
            $countMoney = PayOrder::where("state", ">=", 1)->sum("price");

            $version = Db::query("SELECT VERSION()");
            $mysqlVersion = $version[0]['VERSION()'];

            if (function_exists("gd_info")) {
                $gdInfo = @gd_info();
                $gdVersion = $gdInfo["GD Version"];
            } else {
                $gdVersion = '<font color="red">GD库未开启！</font>';
            }

            return $this->dashboardStatsService()->buildPayload([
                "todayOrder" => $todayOrder,
                "todaySuccessOrder" => $todaySuccessOrder,
                "todayCloseOrder" => $todayCloseOrder,
                "todayMoney" => round((float)$todayMoney, 2),
                "countOrder" => $countOrder,
                "countMoney" => round((float)$countMoney),
            ], [
                "PHP_VERSION" => PHP_VERSION,
                "PHP_OS" => PHP_OS,
                "SERVER" => $_SERVER['SERVER_SOFTWARE'],
                "MySql" => $mysqlVersion,
                "Thinkphp" => "v" . App::VERSION,
                "RunTime" => $this->sys_uptime(),
                "gd" => $gdVersion,
            ]);
        });

        return json($this->getReturn(1, "成功", $statsData));
    }

    public function profile()
    {
        $username = Session::get('admin_user');

        if (!$username) {
            return json([
                'code' => -1,
                'msg' => '没有登录',
                'data' => null,
            ]);
        }

        return json([
            'code' => 1,
            'msg' => '成功',
            'data' => [
                'avatar' => '',
                'username' => (string) $username,
                'nickname' => '管理员',
                'roles' => ['admin'],
                'permissions' => $this->adminPermissionService()->all(),
            ],
        ]);
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        Session::clear();
        Session::destroy();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        return json([
            'code' => 1,
            'msg' => '退出成功',
            'data' => null,
        ]);
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
        $settings = $this->adminSettingsService()->getSettings();

        return json($this->getReturn(1, "成功", $settings));
    }

    /**
     * 保存系统设置
     */
    public function saveSetting()
    {
        $this->adminSettingsService()->saveSettings($this->request->param());

        return json($this->getReturn());
    }

    private function adminSettingsService(): AdminSettingsService
    {
        return $this->app->make(AdminSettingsService::class);
    }

    private function dashboardStatsService(): DashboardStatsService
    {
        return $this->app->make(DashboardStatsService::class);
    }

    private function adminPermissionService(): AdminPermissionService
    {
        return $this->app->make(AdminPermissionService::class);
    }

    /**
     * 生成 RSA 密钥对
     */
    public function generateRsaKeys()
    {
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
            "code" => 1,
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
        PayQrcode::where("id", (int)$this->request->param("id"))->delete();
        return json($this->getReturn());
    }

    /**
     * 获取订单列表
     */
    public function getOrders()
    {
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

        return json($this->getReturn());
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

            $notifyOk = NotifyService::sendNotify($orderData);

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
        PayOrder::where("state", "-1")->delete();
        return json($this->getReturn());
    }

    /**
     * 删除一周前的订单
     */
    public function delLastOrder()
    {
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

}
