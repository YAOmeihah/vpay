<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 安全中间件
 */
class Security
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 检查请求频率限制
        $this->checkRateLimit($request);

        // 处理请求
        $response = $next($request);

        // 添加安全头
        $this->addSecurityHeaders($response);

        return $response;
    }

    /**
     * 检查请求频率限制
     */
    private function checkRateLimit(Request $request): void
    {
        $clientIp = $request->ip();
        $key = 'rate_limit_' . md5($clientIp);
        $requests = cache($key) ?: 0;

        // 每分钟最多1000个请求（更宽松的限制）
        if ($requests >= 1000) {
            abort(429, '请求过于频繁，请稍后重试');
        }

        cache($key, $requests + 1, 60);
    }

    /**
     * 添加安全HTTP头
     */
    private function addSecurityHeaders(Response $response): void
    {
        $headers = config('security.headers', []);

        // ThinkPHP 8中header方法需要数组参数
        if (!empty($headers)) {
            $response->header($headers);
        }

        // 移除敏感信息头
        $response->header([
            'Server' => '',
            'X-Powered-By' => ''
        ]);
    }
}
