<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Session;

class AdminAuth
{
    private const SESSION_TTL = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        if (!Session::has('admin')) {
            return $this->unauthorizedResponse();
        }

        $loginTime = Session::get('login_time');
        if ($loginTime && (time() - $loginTime) > self::SESSION_TTL) {
            Session::clear();
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function unauthorizedResponse(): Response
    {
        return json(['code' => -1, 'msg' => '没有登录', 'data' => null]);
    }
}
