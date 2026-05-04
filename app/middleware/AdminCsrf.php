<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

class AdminCsrf
{
    private const AJAX_HEADER = 'X-Requested-With';
    private const AJAX_VALUE = 'XMLHttpRequest';

    private const SAFE_METHODS = [
        'GET' => true,
        'HEAD' => true,
        'OPTIONS' => true,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isSafeMethod($request) || $this->hasAjaxHeader($request)) {
            return $next($request);
        }

        return json(['code' => 40301, 'msg' => 'CSRF 校验失败', 'data' => null], 403);
    }

    private function isSafeMethod(Request $request): bool
    {
        return isset(self::SAFE_METHODS[strtoupper($request->method())]);
    }

    private function hasAjaxHeader(Request $request): bool
    {
        return strcasecmp((string) $request->header(self::AJAX_HEADER), self::AJAX_VALUE) === 0;
    }
}
