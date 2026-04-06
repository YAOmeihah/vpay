<?php
declare(strict_types=1);

namespace app\service;

use app\model\PayOrder;
use app\model\TmpPrice;
use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\order\OrderStateManager;
use app\service\runtime\MonitorState;
use app\service\runtime\SettingMonitorState;
use think\facade\Db;

class OrderService
{
    /**
     * 创建订单核心逻辑
     * 返回订单数据数组（用于 JSON 响应或 HTML 跳转）
     * @throws \RuntimeException on business logic errors
     */
    public static function createOrder(array $params): array
    {
        $payId = $params['payId'];
        $type = (int)$params['type'];
        $price = (string)$params['price'];
        $param = $params['param'] ?? '';
        $notifyUrl = $params['notifyUrl'] ?? static::systemConfig()->getNotifyUrl();
        $returnUrl = $params['returnUrl'] ?? static::systemConfig()->getReturnUrl();

        // 检查监控端状态
        if (!static::monitorState()->isOnline()) {
            throw new \RuntimeException('监控端状态异常，请检查');
        }

        $orderId = OrderCreationKernel::generatePlatformOrderId();
        $reallyPrice = OrderCreationKernel::reserveUniquePrice($price, $type, $orderId);

        try {
            $payConfig = OrderCreationKernel::resolvePayUrl($type, $reallyPrice);
            OrderCreationKernel::assertMerchantOrderNotExists($payId);

            $createDate = time();
            $data = [
                'close_date'   => 0,
                'create_date'  => $createDate,
                'is_auto'      => $payConfig['isAuto'],
                'notify_url'   => $notifyUrl,
                'order_id'     => $orderId,
                'param'        => $param,
                'pay_date'     => 0,
                'pay_id'       => $payId,
                'pay_url'      => $payConfig['payUrl'],
                'price'        => $price,
                'really_price' => $reallyPrice,
                'return_url'   => $returnUrl,
                'state'        => PayOrder::STATE_UNPAID,
                'type'         => $type,
            ];

            OrderCreationKernel::createOrderRecord($data);
        } catch (\Throwable $e) {
            OrderCreationKernel::rollbackReservedPrice($orderId);
            throw $e;
        }

        return OrderCreationKernel::buildAndCacheOrderInfo(
            $payId,
            $orderId,
            $type,
            $price,
            $reallyPrice,
            $payConfig['payUrl'],
            $payConfig['isAuto'],
            $createDate
        );
    }

    /**
     * 处理支付推送，匹配订单并发送通知
     * 返回: ['matched' => bool, 'alreadyProcessed' => bool, 'notifyOk' => bool, 'notifyDetail' => string]
     */
    public static function handlePayPush(string $price, int $type): array
    {
        static::monitorState()->markPaidAt(time());

        $res = PayOrder::where('really_price', $price)
            ->where('state', PayOrder::STATE_UNPAID)
            ->where('type', $type)
            ->find();

        if (!$res) {
            static::runTransaction(function () use ($price, $type): void {
                $suffix = OrderCreationKernel::generatePlatformOrderId();

                PayOrder::create([
                    'close_date'   => 0,
                    'create_date'  => time(),
                    'is_auto'      => 0,
                    'notify_url'   => '',
                    'order_id'     => '无订单转账-' . $suffix,
                    'param'        => '无订单转账',
                    'pay_date'     => 0,
                    'pay_id'       => '无订单转账-pay-' . $suffix,
                    'pay_url'      => '',
                    'price'        => $price,
                    'really_price' => $price,
                    'return_url'   => '',
                    'state'        => PayOrder::STATE_PAID,
                    'type'         => $type,
                ]);
            });

            return ['matched' => false, 'alreadyProcessed' => false, 'notifyOk' => true, 'notifyDetail' => ''];
        }

        $affected = static::runTransaction(function () use ($res): int {
            // 乐观锁更新，防止并发重复处理
            $affected = PayOrder::where('id', $res['id'])
                ->where('state', PayOrder::STATE_UNPAID)
                ->update(['state' => PayOrder::STATE_PAID, 'pay_date' => time(), 'close_date' => time()]);

            if ($affected === 0) {
                return 0;
            }

            TmpPrice::where('oid', $res['order_id'])->delete();

            return $affected;
        });

        if ($affected === 0) {
            return ['matched' => true, 'alreadyProcessed' => true, 'notifyOk' => true, 'notifyDetail' => ''];
        }

        static::orderStateManager()->invalidateOrderView((string) $res['order_id']);

        $notifyResult = NotifyService::sendNotifyDetailed($res->toArray());
        $notifyOk = $notifyResult['ok'];
        $notifyDetail = $notifyResult['detail'];

        if (!$notifyOk) {
            PayOrder::where('id', $res['id'])->update(['state' => PayOrder::STATE_NOTIFY_FAILED]);
            static::orderStateManager()->invalidateOrderView((string) $res['order_id']);
        }

        return [
            'matched' => true,
            'alreadyProcessed' => false,
            'notifyOk' => $notifyOk,
            'notifyDetail' => $notifyDetail,
        ];
    }

    protected static function systemConfig(): SystemConfig
    {
        return app()->make(SettingSystemConfig::class);
    }

    protected static function monitorState(): MonitorState
    {
        return app()->make(SettingMonitorState::class);
    }

    protected static function orderStateManager(): OrderStateManager
    {
        return app()->make(OrderStateManager::class);
    }

    protected static function runTransaction(callable $callback): mixed
    {
        return Db::transaction($callback);
    }
}
