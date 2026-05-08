<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\security\RateLimitExceededException;
use app\service\security\RateLimitKeyResolver;
use app\service\security\RateLimitPolicyResolver;
use app\service\security\RequestRateLimiter;
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
        try {
            $this->checkRateLimit($request);
        } catch (RateLimitExceededException $exception) {
            $response = $this->rateLimitResponse($exception);
            $this->addSecurityHeaders($response);

            return $response;
        }

        $response = $next($request);

        $this->addSecurityHeaders($response);

        return $response;
    }

    /**
     * 检查请求频率限制
     */
    private function checkRateLimit(Request $request): void
    {
        $policy = $this->rateLimitPolicyResolver()->resolve($request);
        $key = $this->rateLimitKeyResolver()->resolve($policy, $request);

        $this->requestRateLimiter()->assertAllowed($policy, $key);
    }

    private function rateLimitResponse(RateLimitExceededException $exception): Response
    {
        return json([
            'code' => -1,
            'msg' => $exception->getMessage(),
            'data' => [
                'retryAfter' => $exception->retryAfter(),
            ],
        ], 429, [
            'Retry-After' => (string) $exception->retryAfter(),
        ]);
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

    protected function rateLimitPolicyResolver(): RateLimitPolicyResolver
    {
        return app()->make(RateLimitPolicyResolver::class);
    }

    protected function rateLimitKeyResolver(): RateLimitKeyResolver
    {
        return app()->make(RateLimitKeyResolver::class);
    }

    protected function requestRateLimiter(): RequestRateLimiter
    {
        return app()->make(RequestRateLimiter::class);
    }
}
