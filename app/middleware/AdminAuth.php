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
            return json(['code' => -1, 'msg' => '没有登录', 'data' => null]);
        }

        $loginTime = Session::get('login_time');
        if ($loginTime && (time() - $loginTime) > 86400) {
            Session::clear();
            return json(['code' => -1, 'msg' => '没有登录', 'data' => null]);
        }

        return $next($request);
    }
}
