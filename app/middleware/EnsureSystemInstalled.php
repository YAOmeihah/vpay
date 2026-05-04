<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\install\InstallGuardService;
use app\service\install\InstallStateService;
use app\service\update\UpdateStateStore;
use Closure;
use think\Request;
use think\Response;
use think\facade\View;

class EnsureSystemInstalled
{
    private const JSON_PATH_PREFIXES = [
        'admin/index/',
        'merchant/',
        'monitor/',
    ];

    private const JSON_PATHS = [
        'login',
        'enQrcode',
        'createOrder',
        'getOrder',
        'selectOrderPayType',
        'checkOrder',
        'closeOrder',
        'getState',
        'appHeart',
        'appPush',
        'closeEndOrder',
        'payment-test/notify',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $guard = new InstallGuardService();
        if ($guard->shouldBypass($request)) {
            return $next($request);
        }

        $state = $this->installState();
        if (!$guard->shouldBlock((string) ($state['state'] ?? 'installed'))) {
            if ($this->hasUpdateLock() && !$this->shouldAllowDuringUpdate($request)) {
                if ($this->shouldRenderMerchantUnavailablePage($request)) {
                    return $this->merchantUnavailableResponse();
                }

                return json([
                    'code' => 50305,
                    'msg' => '系统正在更新，请稍后再试',
                    'data' => null,
                ], 503);
            }

            return $next($request);
        }

        $stateName = (string) ($state['state'] ?? 'installed');
        if ($this->shouldRenderMerchantUnavailablePage($request)) {
            return $this->merchantUnavailableResponse();
        }

        $installUrl = $guard->installUrl($stateName);
        if (!$this->shouldReturnJson($request)) {
            return response('', 302, ['Location' => $installUrl]);
        }

        $payload = $guard->errorPayload($stateName);

        return json([
            'code' => $payload['code'],
            'msg' => $payload['msg'],
            'data' => ['installUrl' => $installUrl],
        ], 503);
    }

    protected function installState(): array
    {
        return app()->make(InstallStateService::class)->status();
    }

    protected function hasUpdateLock(): bool
    {
        return app()->make(UpdateStateStore::class)->hasLock();
    }

    private function shouldAllowDuringUpdate(Request $request): bool
    {
        $path = ltrim($request->pathinfo(), '/');

        return in_array($path, [
            'admin/index/getUpdateStatus',
            'admin/index/getUpdateRecovery',
        ], true);
    }

    private function shouldReturnJson(Request $request): bool
    {
        $path = ltrim($request->pathinfo(), '/');

        $route = $request->rule();
        if ($route !== null && $route->getOption('response_type') === 'json') {
            return true;
        }

        foreach (self::JSON_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        if (in_array($path, self::JSON_PATHS, true)) {
            return true;
        }

        if (!in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) {
            return true;
        }

        $requestedWith = strtolower((string) $request->header('X-Requested-With'));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept'));

        return str_contains($accept, 'application/json');
    }

    private function shouldRenderMerchantUnavailablePage(Request $request): bool
    {
        $isHtml = (string) $request->post('isHtml', $request->get('isHtml', '0'));

        return ltrim($request->pathinfo(), '/') === 'createOrder'
            && $isHtml === '1';
    }

    private function merchantUnavailableResponse(): Response
    {
        return response(View::fetch('merchant/error', [
            'title' => '支付服务暂不可用',
            'message' => '当前支付服务暂时不可用，请稍后重试。',
            'helpText' => '如已发起支付，请勿重复付款，可返回商户页面稍后再试。',
            'buttonText' => '返回上页',
        ]), 503)->header([
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
}
