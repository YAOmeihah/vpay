<?php
declare(strict_types=1);

namespace app\service;

use app\model\PayOrder;
use app\model\Setting;
use app\model\TmpPrice;

class MonitorService
{
    /**
     * 更新心跳状态
     */
    public static function heartbeat(): void
    {
        Setting::setConfigValue('lastheart', (string)time());
        Setting::setConfigValue('jkstate', '1');
    }

    /**
     * 检查监控端是否超时，超时则标记离线
     */
    public static function checkMonitorTimeout(): void
    {
        $lastheart = Setting::getConfigValue('lastheart');
        if ((time() - intval($lastheart)) > 90) {
            Setting::setConfigValue('jkstate', '0');
        }
    }

    /**
     * 关闭过期订单并清理 TmpPrice
     * 返回关闭的订单数
     */
    public static function closeExpiredOrders(): int
    {
        static::checkMonitorTimeout();

        $time = Setting::getConfigValue('close');
        $closeTime = time() - 60 * intval($time);
        $closeDate = time();

        $affected = PayOrder::where('create_date', '<=', $closeTime)
            ->where('state', PayOrder::STATE_UNPAID)
            ->update(['state' => PayOrder::STATE_EXPIRED, 'close_date' => $closeDate]);

        if ($affected) {
            // 清理刚关闭订单的 TmpPrice
            $rows = PayOrder::where('close_date', $closeDate)->select();
            foreach ($rows as $row) {
                TmpPrice::where('oid', $row['order_id'])->delete();
            }

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
}
