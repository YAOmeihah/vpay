<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\MonitorTerminal;
use app\model\PayOrder;
use app\model\PayQrcode;
use app\model\TmpPrice;
use app\service\NotifyService;
use app\service\admin\AdminPermissionService;
use app\service\admin\AdminSettingsService;
use app\service\admin\ChannelAdminService;
use app\service\admin\DashboardStatsService;
use app\service\admin\TerminalAdminService;
use app\service\order\OrderStateManager;
use app\service\payment\PaymentTestLabService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
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
            return json($this->getReturn(40101, '没有登录', null), 401);
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
        Session::clear();
        Session::destroy();

        return json([
            'code' => 1,
            'msg' => '退出成功',
            'data' => null,
        ]);
    }

    public function createPaymentTestOrder()
    {
        try {
            return json($this->getReturn(
                1,
                '成功',
                $this->paymentTestLabService()->createOrder(
                    (array)$this->request->param(),
                    $this->requestBaseUrl()
                )
            ));
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }
    }

    public function getPaymentTestOrder()
    {
        try {
            return json($this->getReturn(
                1,
                '成功',
                $this->paymentTestLabService()->getOrderStatus((string)$this->request->param('orderId', ''))
            ));
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }
    }

    public function getPaymentTestCallback()
    {
        return json($this->getReturn(1, '成功', $this->paymentTestLabService()->getLatestCallback(
            (string)$this->request->param('orderId', ''),
            (string)$this->request->param('payId', '')
        )));
    }

    /**
     * 获取系统运行时间
     */
    private function sys_uptime()
    {
        $output = '';

        // Linux/Unix系统
        if ($this->currentOsFamily() === 'Linux') {
            $rawUptime = $this->readLinuxUptimeRaw();
            if ($rawUptime !== false && $rawUptime !== '') {
                $str = explode(" ", trim($rawUptime));
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
        if ($this->currentOsFamily() === 'Windows') {
            // Windows下获取系统启动时间
            $uptime = $this->executeShellCommand('wmic os get lastbootuptime /value 2>nul');
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

    protected function currentOsFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    protected function readLinuxUptimeRaw(): string|false
    {
        $path = '/proc/uptime';

        if (!$this->isPathAllowedByOpenBaseDir($path)) {
            return false;
        }

        $content = @file_get_contents($path);
        return $content === false ? false : $content;
    }

    protected function executeShellCommand(string $command): string|false|null
    {
        return shell_exec($command);
    }

    protected function isPathAllowedByOpenBaseDir(string $path): bool
    {
        $openBaseDir = trim((string) ini_get('open_basedir'));
        if ($openBaseDir === '') {
            return true;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        foreach (explode(PATH_SEPARATOR, $openBaseDir) as $allowedPath) {
            $allowedPath = trim($allowedPath);
            if ($allowedPath === '') {
                continue;
            }

            $normalizedAllowedPath = rtrim(str_replace('\\', '/', $allowedPath), '/') . '/';
            if (str_starts_with($normalizedPath . '/', $normalizedAllowedPath)) {
                return true;
            }
        }

        return false;
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

    private function requestBaseUrl(): string
    {
        return $this->request->scheme() . '://' . $this->request->host(true);
    }

    public function getTerminals()
    {
        return json($this->getReturn(1, "成功", $this->terminalAdminService()->paginate($this->request->param())));
    }

    public function getTerminal()
    {
        return json($this->getReturn(1, "成功", $this->terminalAdminService()->find((int) $this->request->param('id'))));
    }

    public function saveTerminal()
    {
        return json($this->getReturn(1, "成功", $this->terminalAdminService()->save($this->request->param())));
    }

    public function toggleTerminal()
    {
        $this->terminalAdminService()->toggle((int) $this->request->param('id'));
        return json($this->getReturn());
    }

    public function deleteTerminal()
    {
        try {
            $this->terminalAdminService()->delete((int) $this->request->param('id'));
            return json($this->getReturn());
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }
    }

    public function resetTerminalKey()
    {
        $key = $this->terminalAdminService()->resetKey((int) $this->request->param('id'));
        return json($this->getReturn(1, "成功", ['monitorKey' => $key]));
    }

    public function getTerminalChannels()
    {
        $terminalId = (int) $this->request->param('terminalId', $this->request->param('terminal_id', 0));
        return json($this->getReturn(1, "成功", $this->channelAdminService()->listForTerminal($terminalId)));
    }

    public function saveTerminalChannel()
    {
        return json($this->getReturn(1, "成功", $this->channelAdminService()->save($this->request->param())));
    }

    public function toggleTerminalChannel()
    {
        $this->channelAdminService()->toggle((int) $this->request->param('id'));
        return json($this->getReturn());
    }

    private function terminalAdminService(): TerminalAdminService
    {
        return $this->app->make(TerminalAdminService::class);
    }

    private function channelAdminService(): ChannelAdminService
    {
        return $this->app->make(ChannelAdminService::class);
    }

    /**
     * 添加支付二维码
     */
    public function addPayQrcode()
    {
        PayQrcode::create([
            "channel_id" => ($this->request->param("channelId", $this->request->param("channel_id")) !== null)
                ? (int) $this->request->param("channelId", $this->request->param("channel_id"))
                : null,
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
        $channelId = $this->request->param("channelId", $this->request->param("channel_id"));

        $query = PayQrcode::where("type", (int)$type);
        if ($channelId !== null && $channelId !== '') {
            $query = $query->where("channel_id", (int) $channelId);
        }
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
     * 解码二维码图片
     */
    public function decodeQrcode()
    {
        $base64 = (string)$this->request->param("base64", "");
        if ($base64 === "") {
            return json($this->getReturn(-1, "图片数据不能为空"));
        }

        $imageBlob = base64_decode($base64, true);
        if ($imageBlob === false || $imageBlob === "") {
            return json($this->getReturn(-1, "图片数据无效"));
        }

        try {
            $options = new QROptions([
                'readerUseImagickIfAvailable' => true,
            ]);
            $decoded = trim((string)(new QRCode($options))->readFromBlob($imageBlob));

            if ($decoded === "") {
                return json($this->getReturn(-2, "二维码识别失败"));
            }

            return json($this->getReturn(1, "成功", $decoded));
        } catch (\Throwable $e) {
            return json($this->getReturn(-2, "二维码识别失败"));
        }
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

            $notifyResult = NotifyService::sendNotifyDetailed($orderData);
            $notifyOk = $notifyResult['ok'];

            if ($notifyOk) {
                if ($res['state'] == 0) {
                    TmpPrice::where("oid", $res['order_id'])->delete();
                }

                PayOrder::where("id", $res['id'])->update(array("state" => 1));
                $this->orderStateManager()->invalidateOrderView((string) $res['order_id']);
                return json($this->getReturn());
            } else {
                $detail = trim((string)($notifyResult['detail'] ?? ''));
                return json($this->getReturn(-2, "补单失败，异步通知返回错误", $detail !== '' ? $detail : null));
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
        $orderIds = PayOrder::where("state", "-1")->column('order_id');
        PayOrder::where("state", "-1")->delete();
        $this->orderStateManager()->invalidateOrderViews($orderIds);
        return json($this->getReturn());
    }

    /**
     * 删除一周前的订单
     */
    public function delLastOrder()
    {
        $orderIds = PayOrder::where("create_date", "<", (time() - 604800))->column('order_id');
        PayOrder::where("create_date", "<", (time() - 604800))->delete();
        $this->orderStateManager()->invalidateOrderViews($orderIds);
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

    private function orderStateManager(): OrderStateManager
    {
        return $this->app->make(OrderStateManager::class);
    }

    private function paymentTestLabService(): PaymentTestLabService
    {
        return $this->app->make(PaymentTestLabService::class);
    }

}
