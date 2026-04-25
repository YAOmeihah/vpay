<?php
declare(strict_types=1);

namespace app\service;

use app\model\MonitorTerminal;
use app\model\PayOrder;
use app\model\Setting;
use app\model\TerminalChannel;
use app\model\TmpPrice;
use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;
use app\service\order\OrderStateManager;
use app\service\payment\PaymentEventService;
use app\service\terminal\ChannelPriceReservationService;
use app\service\terminal\TerminalAllocatorService;
use app\service\terminal\TerminalAllocationCursorService;
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

        $orderId = OrderCreationKernel::generatePlatformOrderId();
        OrderCreationKernel::assertMerchantOrderNotExists($payId);
        $selection = static::runTransaction(function () use (
            $payId,
            $type,
            $price,
            $param,
            $notifyUrl,
            $returnUrl,
            $orderId
        ): array {
            try {
                [$channel, $payConfig, $reallyPrice] = static::selectAvailableChannelForOrder($type, $price, $orderId);
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
                    'terminal_id'  => (int) $channel['terminal_id'],
                    'channel_id'   => (int) $channel['id'],
                    'assign_status' => 'assigned',
                    'assign_reason' => '',
                    'terminal_snapshot' => (string) $channel['terminal_name'],
                    'channel_snapshot' => (string) $channel['channel_name'],
                    'state'        => PayOrder::STATE_UNPAID,
                    'type'         => $type,
                ];

                OrderCreationKernel::createOrderRecord($data);
                static::markChannelUsed((int) $channel['id'], $createDate);
                static::markAllocationCursorUsed($type, (int) $channel['id']);

                return [$channel, $payConfig, $reallyPrice, $createDate];
            } catch (\Throwable $e) {
                OrderCreationKernel::rollbackReservedPrice($orderId);
                throw $e;
            }
        });

        [$channel, $payConfig, $reallyPrice, $createDate] = $selection;

        return OrderCreationKernel::buildAndCacheOrderInfo(
            $payId,
            $orderId,
            $type,
            $price,
            $reallyPrice,
            $payConfig['payUrl'],
            $payConfig['isAuto'],
            $createDate,
            (int) $channel['terminal_id'],
            (int) $channel['id'],
            (string) $channel['terminal_name'],
            (string) $channel['channel_name']
        );
    }

    /**
     * 处理支付推送，匹配订单并发送通知
     * 返回: ['matched' => bool, 'alreadyProcessed' => bool, 'notifyOk' => bool, 'notifyDetail' => string]
     */
    public static function handleTerminalPayPush(
        int $terminalId,
        string $price,
        int $type,
        string $eventId,
        array $rawPayload
    ): array {
        static::markTerminalPaid($terminalId, time());

        return static::processPayPush($terminalId, $price, $type, $eventId, $rawPayload);
    }

    protected static function systemConfig(): SystemConfig
    {
        return app()->make(SettingSystemConfig::class);
    }

    protected static function orderStateManager(): OrderStateManager
    {
        return app()->make(OrderStateManager::class);
    }

    protected static function runTransaction(callable $callback): mixed
    {
        return Db::transaction($callback);
    }

    protected static function paymentEventService(): PaymentEventService
    {
        return app()->make(PaymentEventService::class);
    }

    protected static function allocator(): TerminalAllocatorService
    {
        return app()->make(TerminalAllocatorService::class);
    }

    protected static function allocationCursor(): TerminalAllocationCursorService
    {
        return app()->make(TerminalAllocationCursorService::class);
    }

    protected static function priceReservation(): ChannelPriceReservationService
    {
        return app()->make(ChannelPriceReservationService::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function selectChannel(int $type): array
    {
        $channels = static::loadChannelsForType($type);
        $lastChannelId = static::lastChannelIdForStrategy($type, $channels);

        return static::allocator()->pickChannel(static::allocationStrategy(), $channels, $type, $lastChannelId);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array{payUrl: string, isAuto: int}, 2: string}
     */
    protected static function selectAvailableChannelForOrder(int $type, string $price, string $orderId): array
    {
        $orderedChannels = static::orderedChannels($type);
        $lastException = null;

        foreach ($orderedChannels as $channel) {
            try {
                $reallyPrice = static::priceReservation()->reserve(
                    $price,
                    (int) $channel['id'],
                    $orderId,
                    static::systemConfig()->getPayQfMode()
                );
                $payConfig = OrderCreationKernel::resolvePayUrlForChannel((int) $channel['id'], $type, $reallyPrice);

                return [$channel, $payConfig, $reallyPrice];
            } catch (\RuntimeException $e) {
                OrderCreationKernel::rollbackReservedPrice($orderId);

                if (!static::shouldTryNextChannel($e)) {
                    throw $e;
                }

                $lastException = $e;
            }
        }

        throw $lastException ?? new \RuntimeException($type === 1 ? '当前无可用微信收款终端' : '当前无可用支付宝收款终端');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function orderedChannels(int $type): array
    {
        $channels = static::loadChannelsForType($type);
        $lastChannelId = static::lastChannelIdForStrategy($type, $channels);

        return static::allocator()->orderEligibleChannels(static::allocationStrategy(), $channels, $type, $lastChannelId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function loadChannelsForType(int $type): array
    {
        $rows = [];
        foreach (TerminalChannel::where('type', $type)->select() as $channel) {
            $terminal = MonitorTerminal::where('id', (int) $channel['terminal_id'])->find();
            if (!$terminal) {
                continue;
            }

            $rows[] = [
                'id' => (int) $channel['id'],
                'terminal_id' => (int) $channel['terminal_id'],
                'type' => (int) $channel['type'],
                'channel_name' => (string) $channel['channel_name'],
                'status' => (string) $channel['status'],
                'pay_url' => (string) $channel['pay_url'],
                'last_used_at' => (int) $channel['last_used_at'],
                'terminal_name' => (string) $terminal['terminal_name'],
                'terminal_status' => (string) $terminal['status'],
                'online_state' => (string) $terminal['online_state'],
                'dispatch_priority' => (int) ($terminal['dispatch_priority'] ?? 100),
            ];
        }

        return $rows;
    }

    protected static function allocationStrategy(): string
    {
        return Setting::getConfigValue('allocationStrategy', 'fixed_priority');
    }

    protected static function shouldTryNextChannel(\RuntimeException $e): bool
    {
        return in_array($e->getMessage(), [
            '请您先进入后台配置程序',
            '当前通道不存在或已被删除',
            '订单超出负荷，请稍后重试',
        ], true);
    }

    /**
     * @param array<int, array<string, mixed>> $channels
     */
    protected static function lastChannelIdForStrategy(int $type, array $channels): ?int
    {
        if (static::allocationStrategy() === 'round_robin') {
            return static::allocationCursor()->lastChannelIdForUpdate($type);
        }

        return static::lastUsedChannelId($channels);
    }

    protected static function markAllocationCursorUsed(int $type, int $channelId): void
    {
        if (static::allocationStrategy() === 'round_robin') {
            static::allocationCursor()->markChannelUsed($type, $channelId);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $channels
     */
    protected static function lastUsedChannelId(array $channels): ?int
    {
        usort($channels, static fn (array $left, array $right): int => [
            (int) ($right['last_used_at'] ?? 0),
            (int) ($right['id'] ?? 0),
        ] <=> [
            (int) ($left['last_used_at'] ?? 0),
            (int) ($left['id'] ?? 0),
        ]);

        $channel = $channels[0] ?? null;
        if ($channel === null || (int) ($channel['last_used_at'] ?? 0) <= 0) {
            return null;
        }

        return (int) $channel['id'];
    }

    protected static function markChannelUsed(int $channelId, int $timestamp): void
    {
        TerminalChannel::where('id', $channelId)->update([
            'last_used_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    protected static function markTerminalPaid(int $terminalId, int $timestamp): void
    {
        MonitorTerminal::where('id', $terminalId)->update([
            'last_paid_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @param array<string, mixed> $rawPayload
     */
    private static function processPayPush(
        int $terminalId,
        string $price,
        int $type,
        string $eventId = '',
        array $rawPayload = []
    ): array {
        $res = PayOrder::where('really_price', $price)
            ->where('state', PayOrder::STATE_UNPAID)
            ->where('type', $type);

        $res->where('terminal_id', $terminalId);

        $matchedOrder = $res->find();

        if (!$matchedOrder) {
            if ($eventId !== '') {
                static::paymentEventService()->recordUnmatched(
                    $terminalId,
                    $eventId,
                    $type,
                    (int) bcmul($price, '100', 0),
                    $rawPayload
                );
            }

            static::runTransaction(function () use ($terminalId, $price, $type): void {
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
                    'terminal_id'  => $terminalId,
                    'state'        => PayOrder::STATE_PAID,
                    'type'         => $type,
                ]);
            });

            return ['matched' => false, 'alreadyProcessed' => false, 'notifyOk' => true, 'notifyDetail' => ''];
        }

        $affected = static::runTransaction(function () use ($matchedOrder): int {
            $affected = PayOrder::where('id', $matchedOrder['id'])
                ->where('state', PayOrder::STATE_UNPAID)
                ->update(['state' => PayOrder::STATE_PAID, 'pay_date' => time(), 'close_date' => time()]);

            if ($affected === 0) {
                return 0;
            }

            TmpPrice::where('oid', $matchedOrder['order_id'])->delete();

            return $affected;
        });

        if ($affected === 0) {
            return ['matched' => true, 'alreadyProcessed' => true, 'notifyOk' => true, 'notifyDetail' => ''];
        }

        if ($eventId !== '') {
            static::paymentEventService()->recordMatched(
                $terminalId,
                (int) ($matchedOrder['channel_id'] ?? 0),
                $eventId,
                $type,
                (int) bcmul($price, '100', 0),
                (string) $matchedOrder['order_id'],
                $rawPayload
            );
        }

        static::orderStateManager()->invalidateOrderView((string) $matchedOrder['order_id']);

        $notifyResult = NotifyService::sendNotifyDetailed($matchedOrder->toArray());
        $notifyOk = $notifyResult['ok'];
        $notifyDetail = $notifyResult['detail'];

        if (!$notifyOk) {
            PayOrder::where('id', $matchedOrder['id'])->update(['state' => PayOrder::STATE_NOTIFY_FAILED]);
            static::orderStateManager()->invalidateOrderView((string) $matchedOrder['order_id']);
        }

        return [
            'matched' => true,
            'alreadyProcessed' => false,
            'notifyOk' => $notifyOk,
            'notifyDetail' => $notifyDetail,
        ];
    }
}
