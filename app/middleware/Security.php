<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\security\LoginAttemptLimiter;
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
        $limiter = $this->loginAttemptLimiter();

        if ($limiter->tooManyRequests($clientIp)) {
            abort(429, '请求过于频繁，请稍后重试');
        }

        $limiter->recordRequest($clientIp);
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

    private function loginAttemptLimiter(): LoginAttemptLimiter
    {
        return new LoginAttemptLimiter();
    }
}
