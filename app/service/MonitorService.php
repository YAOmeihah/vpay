<?php
declare(strict_types=1);

namespace app\service;

use app\model\MonitorTerminal;
use app\model\PayOrder;
use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\notification\NotificationService;
use app\service\order\ExpiredOrderCleanupGate;
use app\service\order\OrderStateManager;
use think\facade\Db;

class MonitorService
{
    public const TERMINAL_HEARTBEAT_TIMEOUT_SECONDS = 90;

    private const REQUEST_CLEANUP_THROTTLE_SECONDS = 5;

    public static function heartbeatForTerminal(int $terminalId, string $ip): void
    {
        $terminal = static::findTerminal($terminalId);
        $wasOffline = $terminal !== null && (string) $terminal->online_state === 'offline';

        $timestamp = static::currentTimestamp();
        static::persistTerminalHeartbeat($terminalId, $ip, $timestamp);

        if ($wasOffline && $terminal !== null) {
            static::notificationService()->terminalRecovered($terminal->toArray());
        }
    }

    /**
     * 检查监控端是否超时，超时则标记离线
     */
    public static function checkMonitorTimeout(): int
    {
        $threshold = static::currentTimestamp() - self::TERMINAL_HEARTBEAT_TIMEOUT_SECONDS;
        $terminals = MonitorTerminal::where('online_state', 'online')
            ->where('last_heartbeat_at', '<', $threshold)
            ->select()
            ->toArray();

        $affected = 0;

        foreach ($terminals as $terminal) {
            $terminalId = (int) ($terminal['id'] ?? 0);
            if ($terminalId <= 0) {
                continue;
            }

            $updated = MonitorTerminal::where('id', $terminalId)
                ->where('online_state', 'online')
                ->where('last_heartbeat_at', '<', $threshold)
                ->update([
                    'online_state' => 'offline',
                    'updated_at' => static::currentTimestamp(),
                ]);

            if ($updated) {
                $affected += (int) $updated;
                static::notificationService()->terminalOffline($terminal);
            }
        }

        return $affected;
    }

    /**
     * 关闭过期订单并清理 TmpPrice
     * 返回关闭的订单数
     */
    public static function closeExpiredOrders(bool $force = false, bool $checkMonitorTimeout = true): int
    {
        if ($checkMonitorTimeout) {
            static::checkMonitorTimeout();
        }

        if (!static::cleanupGate()->shouldRun($force)) {
            return 0;
        }

        $closeTime = time() - 60 * static::systemConfig()->getOrderCloseMinutes();
        $closeDate = time();
        $orderIds = Db::name('pay_order')
            ->where('create_date', '<=', $closeTime)
            ->where('state', PayOrder::STATE_UNPAID)
            ->column('order_id');

        $affected = 0;

        if ($orderIds !== []) {
            $affected = Db::name('pay_order')
                ->whereIn('order_id', $orderIds)
                ->where('state', PayOrder::STATE_UNPAID)
                ->update(['state' => PayOrder::STATE_EXPIRED, 'close_date' => $closeDate]);
        }

        if ($affected) {
            // 清理刚关闭订单的 TmpPrice
            foreach ($orderIds as $orderId) {
                Db::name('tmp_price')->where('oid', $orderId)->delete();
            }
            static::orderStateManager()->invalidateOrderViews($orderIds);
            static::notificationService()->expiredOrdersClosed((int) $affected);
        }

        static::cleanOrphanTmpPrices();

        return (int)$affected;
    }

    /**
     * 清理无对应订单的 TmpPrice 记录
     */
    private static function cleanOrphanTmpPrices(): int
    {
        return Db::name('tmp_price')->whereNotIn('oid', static function ($query): void {
            $query->name('pay_order')->whereNotNull('order_id')->field('order_id');
        })->delete();
    }

    protected static function currentTimestamp(): int
    {
        return time();
    }

    protected static function persistTerminalHeartbeat(int $terminalId, string $ip, int $timestamp): void
    {
        MonitorTerminal::where('id', $terminalId)->update([
            'last_heartbeat_at' => $timestamp,
            'last_ip' => $ip,
            'online_state' => 'online',
            'updated_at' => $timestamp,
        ]);
    }

    protected static function findTerminal(int $terminalId): ?MonitorTerminal
    {
        return MonitorTerminal::where('id', $terminalId)->find();
    }

    protected static function systemConfig(): SystemConfig
    {
        return app()->make(SettingSystemConfig::class);
    }

    protected static function cleanupGate(): ExpiredOrderCleanupGate
    {
        return app()->make(ExpiredOrderCleanupGate::class, [
            'ttlSeconds' => self::REQUEST_CLEANUP_THROTTLE_SECONDS,
        ]);
    }

    protected static function orderStateManager(): OrderStateManager
    {
        return app()->make(OrderStateManager::class);
    }

    protected static function notificationService(): NotificationService
    {
        return app()->make(NotificationService::class);
    }
}
