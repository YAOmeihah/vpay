<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * 安全审计日志服务
 */
class SecurityLog
{
    /**
     * 记录登录成功
     */
    public static function loginSuccess(string $username, string $ip): void
    {
        self::log('login_success', [
            'username' => $username,
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    /**
     * 记录登录失败
     */
    public static function loginFailed(string $username, string $ip, string $reason = ''): void
    {
        self::log('login_failed', [
            'username' => $username,
            'ip' => $ip,
            'reason' => $reason,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    /**
     * 记录管理员操作
     */
    public static function adminAction(string $action, array $data = []): void
    {
        self::log('admin_action', [
            'action' => $action,
            'data' => $data,
            'ip' => request()->ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    /**
     * 记录订单创建
     */
    public static function orderCreate(string $orderId, float $amount, int $type): void
    {
        self::log('order_create', [
            'order_id' => $orderId,
            'amount' => $amount,
            'type' => $type,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * 记录设置变更
     */
    public static function settingChange(string $key, string $oldValue, string $newValue): void
    {
        self::log('setting_change', [
            'key' => $key,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * 记录安全事件
     */
    public static function securityEvent(string $event, array $data = []): void
    {
        self::log('security_event', [
            'event' => $event,
            'data' => $data,
            'ip' => request()->ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    /**
     * 写入日志
     */
    private static function log(string $type, array $data): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'data' => $data,
        ];

        Log::channel('security')->info(json_encode($logData, JSON_UNESCAPED_UNICODE));
    }
}
