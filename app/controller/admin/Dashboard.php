<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\PayOrder;
use app\service\admin\DashboardStatsService;
use think\App;
use think\facade\Db;
use think\facade\Log;

class Dashboard extends BaseController
{
    use \app\controller\trait\ApiResponse;

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

            $mysqlVersion = (string) Db::connect()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

            if (function_exists("gd_info")) {
                $gdInfo = @gd_info();
                $gdVersion = $gdInfo["GD Version"];
            } else {
                $gdVersion = 'GD库未开启';
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

        return $this->success($statsData);
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
            $uptime = $this->readWindowsLastBootUptimeRaw();
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
            Log::warning('Linux uptime file is blocked by open_basedir.', ['path' => $path]);
            return false;
        }

        if (!is_readable($path)) {
            Log::warning('Linux uptime file is not readable.', ['path' => $path]);
            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            Log::warning('Linux uptime file read failed.', ['path' => $path]);
            return false;
        }

        return $content;
    }

    protected function readWindowsLastBootUptimeRaw(): string|false|null
    {
        return shell_exec('wmic os get lastbootuptime /value 2>nul');
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

    private function dashboardStatsService(): DashboardStatsService
    {
        return $this->app->make(DashboardStatsService::class);
    }
}
