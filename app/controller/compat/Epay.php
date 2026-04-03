<?php
declare(strict_types=1);

namespace app\controller\compat;

use app\BaseController;
use app\service\epay\EpayOrderService;
use app\service\epay\EpayResponseService;
use think\facade\Log;

class Epay extends BaseController
{
    public function mapi()
    {
        try {
            $payload = EpayOrderService::create($this->request->param());
            return json(EpayResponseService::success($payload));
        } catch (\Throwable $e) {
            Log::error('EPay mapi error: ' . $e->getMessage());
            return json(EpayResponseService::fail('请求失败'));
        }
    }

    public function submit()
    {
        try {
            $payload = EpayOrderService::create($this->request->param());
            $tradeNo = rawurlencode((string)$payload['trade_no']);
            $qrcode = htmlspecialchars((string)$payload['qrcode'], ENT_QUOTES, 'UTF-8');
            $rawPayurl = (string)$payload['payurl'];
            $payurlScheme = strtolower((string)parse_url($rawPayurl, PHP_URL_SCHEME));
            if (!in_array($payurlScheme, ['http', 'https'], true)) {
                $rawPayurl = '#';
            }
            $payurl = htmlspecialchars($rawPayurl, ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string)($this->request->param('name') ?: '订单支付'), ENT_QUOTES, 'UTF-8');

            $html = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .pay-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 32px 24px;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .pay-title {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 12px;
            font-weight: 600;
        }
        .pay-desc {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .pay-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .pay-btn {
            display: block;
            width: 100%;
            text-decoration: none;
            background: #2563eb;
            color: #fff;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 16px;
            font-weight: 600;
        }
        .pay-link {
            font-size: 13px;
            color: #2563eb;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="pay-card">
        <h1 class="pay-title">' . $title . '</h1>
        <p class="pay-desc">订单已创建，请继续完成支付。</p>
        <div class="pay-actions">
            <a class="pay-btn" href="' . $payurl . '">立即支付</a>
            <a class="pay-btn" href="payPage/pay.html?orderId=' . $tradeNo . '">打开扫码页</a>
            <div class="pay-link">支付链接：' . $qrcode . '</div>
        </div>
    </div>
</body>
</html>';

            return response($html)->header(['Content-Type' => 'text/html; charset=utf-8']);
        } catch (\Throwable $e) {
            Log::error('EPay submit error: ' . $e->getMessage());
            return response(static::renderErrorPage('请求失败'))
                ->header(['Content-Type' => 'text/html; charset=utf-8']);
        }
    }

    public function createV2()
    {
        try {
            $payload = EpayOrderService::createV2($this->request->param());
            return json(EpayResponseService::successV2($payload));
        } catch (\Throwable $e) {
            Log::error('EPay v2 create error: ' . $e->getMessage());
            return json(EpayResponseService::fail('请求失败'));
        }
    }

    public function submitV2()
    {
        try {
            $payload = EpayOrderService::createV2($this->request->param());
            $tradeNo = rawurlencode((string)$payload['trade_no']);
            $rawPayInfo = (string)$payload['pay_info'];
            $payInfoScheme = strtolower((string)parse_url($rawPayInfo, PHP_URL_SCHEME));
            if (!in_array($payInfoScheme, ['http', 'https', 'weixin', 'alipays'], true)) {
                $rawPayInfo = '#';
            }
            $payInfo = htmlspecialchars($rawPayInfo, ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string)($this->request->param('name') ?: '订单支付'), ENT_QUOTES, 'UTF-8');

            $html = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .pay-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 32px 24px;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .pay-title {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 12px;
            font-weight: 600;
        }
        .pay-desc {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .pay-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .pay-btn {
            display: block;
            width: 100%;
            text-decoration: none;
            background: #2563eb;
            color: #fff;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 16px;
            font-weight: 600;
        }
        .pay-link {
            font-size: 13px;
            color: #2563eb;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="pay-card">
        <h1 class="pay-title">' . $title . '</h1>
        <p class="pay-desc">订单已创建，请继续完成支付。</p>
        <div class="pay-actions">
            <a class="pay-btn" href="' . $payInfo . '">立即支付</a>
            <a class="pay-btn" href="payPage/pay.html?orderId=' . $tradeNo . '">打开扫码页</a>
            <div class="pay-link">支付链接：' . $payInfo . '</div>
        </div>
    </div>
</body>
</html>';

            return response($html)->header(['Content-Type' => 'text/html; charset=utf-8']);
        } catch (\Throwable $e) {
            Log::error('EPay v2 submit error: ' . $e->getMessage());
            return response(static::renderErrorPage('请求失败'))
                ->header(['Content-Type' => 'text/html; charset=utf-8']);
        }
    }

    private static function renderErrorPage(string $message): string
    {
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付异常</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 40px 30px;
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .error-icon {
            font-size: 48px;
            color: #ff6b6b;
            margin-bottom: 20px;
        }
        .error-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .error-message {
            font-size: 16px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 30px;
        }
        .retry-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .retry-btn:hover {
            background: #5a6fd8;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">支付异常</h1>
        <p class="error-message">' . $message . '</p>
        <button class="retry-btn" onclick="history.back()">返回上页</button>
    </div>
</body>
</html>';
    }
}
