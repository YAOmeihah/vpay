<?php
declare(strict_types=1);

namespace app\service;

use app\model\PayOrder;
use app\model\TmpPrice;
use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\order\ExpiredOrderCleanupGate;
use app\service\order\OrderStateManager;
use app\service\runtime\MonitorState;
use app\service\runtime\SettingMonitorState;

class MonitorService
{
    private const REQUEST_CLEANUP_THROTTLE_SECONDS = 5;

    /**
     * 更新心跳状态
     */
    public static function heartbeat(): void
    {
        $state = static::monitorState();
        $state->markHeartbeatAt(time());
        $state->markOnline();
    }

    /**
     * 检查监控端是否超时，超时则标记离线
     */
    public static function checkMonitorTimeout(): void
    {
        if ((time() - static::monitorState()->getLastHeartbeatAt()) > 90) {
            static::monitorState()->markOffline();
        }
    }

    /**
     * 关闭过期订单并清理 TmpPrice
     * 返回关闭的订单数
     */
    public static function closeExpiredOrders(bool $force = false): int
    {
        static::checkMonitorTimeout();

        if (!static::cleanupGate()->shouldRun($force)) {
            return 0;
        }

        $closeTime = time() - 60 * static::systemConfig()->getOrderCloseMinutes();
        $closeDate = time();

        $affected = PayOrder::where('create_date', '<=', $closeTime)
            ->where('state', PayOrder::STATE_UNPAID)
            ->update(['state' => PayOrder::STATE_EXPIRED, 'close_date' => $closeDate]);

        if ($affected) {
            // 清理刚关闭订单的 TmpPrice
            $orderIds = PayOrder::where('close_date', $closeDate)
                ->where('state', PayOrder::STATE_EXPIRED)
                ->column('order_id');
            foreach ($orderIds as $orderId) {
                TmpPrice::where('oid', $orderId)->delete();
            }
            static::orderStateManager()->invalidateOrderViews($orderIds);

            // 清理孤儿 TmpPrice（无对应订单）
            static::cleanOrphanTmpPrices();
        }

        return (int)$affected;
    }

    /**
     * 清理无对应订单的 TmpPrice 记录
     * 注意：当前实现为全表扫描，数据量大时需优化为 JOIN 查询
     */
    private static function cleanOrphanTmpPrices(): void
    {
        $rows = TmpPrice::select();
        foreach ($rows as $row) {
            $exists = PayOrder::where('order_id', $row['oid'])->find();
            if (!$exists) {
                TmpPrice::where('oid', $row['oid'])->delete();
            }
        }
    }

    protected static function monitorState(): MonitorState
    {
        return app()->make(SettingMonitorState::class);
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
}
