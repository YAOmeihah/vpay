<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\facade\Session;
use think\Request;
use think\Response;

class AdminCsrf
{
    public const TOKEN_HEADER = 'X-CSRF-Token';
    private const SESSION_KEY = 'admin_csrf_token';

    private const SAFE_METHODS = [
        'GET' => true,
        'HEAD' => true,
        'OPTIONS' => true,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isSafeMethod($request) || $this->isLoginRequest($request) || $this->hasValidToken($request)) {
            return $next($request);
        }

        return json(['code' => 40301, 'msg' => 'CSRF 校验失败', 'data' => null], 403);
    }

    public static function refreshToken(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);

        return $token;
    }

    private function isSafeMethod(Request $request): bool
    {
        return isset(self::SAFE_METHODS[strtoupper($request->method())]);
    }

    private function isLoginRequest(Request $request): bool
    {
        return trim($request->pathinfo(), '/') === 'login';
    }

    private function hasValidToken(Request $request): bool
    {
        $expected = (string) Session::get(self::SESSION_KEY, '');
        $actual = (string) $request->header(self::TOKEN_HEADER, '');

        return $expected !== '' && $actual !== '' && hash_equals($expected, $actual);
    }
}
