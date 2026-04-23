<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Session;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Session::has('admin')) {
            return $this->unauthorizedResponse();
        }

        $loginTime = Session::get('login_time');
        if ($loginTime && (time() - $loginTime) > $this->sessionTtl()) {
            Session::clear();
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function unauthorizedResponse(): Response
    {
        return json(['code' => 40101, 'msg' => '没有登录', 'data' => null], 401);
    }

    private function sessionTtl(): int
    {
        return (int) config('security.login.session_timeout', 28800);
    }
}
