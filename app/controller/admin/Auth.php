<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\AdminSettingsService;
use app\service\security\LoginAttemptLimiter;
use think\facade\Session;

class Auth extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function login()
    {
        $user = trim($this->request->param("user"));
        $pass = trim($this->request->param("pass"));

        if (!$user || $user == "" || !$pass || $pass == "") {
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        if (strlen($user) > 100 || strlen($pass) > 200) {
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        $clientIp = $this->request->ip();
        $limiter = $this->loginAttemptLimiter();

        if ($limiter->tooManyLoginAttempts($clientIp)) {
            return json($this->getReturn(-1, "登录失败次数过多，请5分钟后重试"));
        }

        $settings = $this->adminSettingsService();
        $_user = $settings->getAdminUsername();
        $_pass = $settings->getAdminPasswordHash();

        if (!hash_equals((string) $_user, $user) || !password_verify($pass, $_pass)) {
            $limiter->recordLoginFailure($clientIp);
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        $limiter->clearLoginAttempts($clientIp);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        Session::set("admin", 1);
        Session::set("admin_user", (string) $_user);
        Session::set("login_time", time());
        Session::set("login_ip", $clientIp);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(false);
        }

        return json($this->getReturn(1, "登录成功"));
    }

    private function adminSettingsService(): AdminSettingsService
    {
        return $this->app->make(AdminSettingsService::class);
    }

    private function loginAttemptLimiter(): LoginAttemptLimiter
    {
        return $this->app->make(LoginAttemptLimiter::class);
    }
}
