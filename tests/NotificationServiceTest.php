<?php
declare(strict_types=1);

namespace tests;

use app\model\PayOrder;
use app\service\OrderService;
use app\service\notification\NotificationService;
use app\service\notification\TelegramNotificationChannel;
use think\facade\Db;

final class NotificationServiceTest extends TestCase
{
    public function test_skips_notifications_when_telegram_is_disabled(): void
    {
        $channel = new FakeTelegramChannel();
        $service = new TestNotificationService($channel);

        $service->terminalOffline(['terminal_code' => 'T1', 'terminal_name' => 'Terminal 1']);

        self::assertSame([], $channel->messages);
    }

    public function test_sends_terminal_offline_message_when_event_enabled(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_terminal_offline' => '1',
        ]);
        $channel = new FakeTelegramChannel();
        $service = new TestNotificationService($channel);

        $service->terminalOffline(['terminal_code' => 'T1', 'terminal_name' => 'Terminal 1']);

        self::assertCount(1, $channel->messages);
        self::assertStringContainsString('监控端离线', $channel->messages[0]);
        self::assertStringContainsString('T1', $channel->messages[0]);
    }

    public function test_event_switch_prevents_recovery_message(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_terminal_recovered' => '0',
        ]);
        $channel = new FakeTelegramChannel();
        $service = new TestNotificationService($channel);

        $service->terminalRecovered(['terminal_code' => 'T1', 'terminal_name' => 'Terminal 1']);

        self::assertSame([], $channel->messages);
    }

    public function test_sends_expired_order_cleanup_only_when_count_is_positive(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
        ]);
        $channel = new FakeTelegramChannel();
        $service = new TestNotificationService($channel);

        $service->expiredOrdersClosed(0);
        $service->expiredOrdersClosed(3);

        self::assertCount(1, $channel->messages);
        self::assertStringContainsString('关闭过期订单', $channel->messages[0]);
        self::assertStringContainsString('3', $channel->messages[0]);
    }

    public function test_sends_test_message(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
        ]);
        $channel = new FakeTelegramChannel();
        $service = new TestNotificationService($channel);

        self::assertTrue($service->sendTestMessage());
        self::assertStringContainsString('维护通知测试', $channel->messages[0]);
    }

    public function test_sends_payment_success_message_with_callback_status_when_enabled(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_payment_success' => '1',
            'notify_payment_success_callback_status' => '1',
        ]);
        $channel = new FakeTelegramChannel();
        $service = new TestNotificationService($channel);

        $service->paymentSuccess([
            'order_id' => 'cloud-order-001',
            'pay_id' => 'merchant-pay-001',
            'price' => '10.00',
            'really_price' => '10.00',
            'type' => PayOrder::TYPE_WECHAT,
        ], true, '');

        self::assertCount(1, $channel->messages);
        self::assertStringContainsString('支付成功', $channel->messages[0]);
        self::assertStringContainsString('cloud-order-001', $channel->messages[0]);
        self::assertStringContainsString('merchant-pay-001', $channel->messages[0]);
        self::assertStringContainsString('商户回调: 成功', $channel->messages[0]);
    }

    public function test_payment_success_message_respects_event_and_callback_status_switches(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_payment_success' => '0',
            'notify_payment_success_callback_status' => '1',
        ]);
        $channel = new FakeTelegramChannel();
        $service = new TestNotificationService($channel);
        $order = [
            'order_id' => 'cloud-order-muted',
            'pay_id' => 'merchant-muted',
            'price' => '10.00',
            'really_price' => '10.00',
            'type' => PayOrder::TYPE_ALIPAY,
        ];

        $service->paymentSuccess($order, false, 'timeout');

        self::assertSame([], $channel->messages);

        $this->seedSettings([
            'notify_event_payment_success' => '1',
            'notify_payment_success_callback_status' => '0',
        ]);

        $service->paymentSuccess($order, false, 'timeout');

        self::assertCount(1, $channel->messages);
        self::assertStringNotContainsString('商户回调', $channel->messages[0]);
    }

    public function test_terminal_payment_push_sends_payment_success_notification_with_callback_result(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_payment_success' => '1',
            'notify_payment_success_callback_status' => '1',
        ]);
        PayOrder::create([
            'close_date' => 0,
            'create_date' => time(),
            'is_auto' => 0,
            'notify_url' => 'ftp://merchant.example/notify',
            'order_id' => 'cloud-order-push',
            'param' => 'param',
            'pay_date' => 0,
            'pay_id' => 'merchant-pay-push',
            'pay_url' => '',
            'price' => '10.00',
            'really_price' => '10.00',
            'return_url' => 'https://merchant.example/return',
            'sign_type' => 'MD5',
            'terminal_id' => 7,
            'channel_id' => 17,
            'assign_status' => PayOrder::ASSIGN_STATUS_ASSIGNED,
            'assign_reason' => '',
            'terminal_snapshot' => 'Terminal 7',
            'channel_snapshot' => 'Wechat 7',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_WECHAT,
        ]);
        $channel = new FakeTelegramChannel();
        TestOrderServiceWithPaymentNotifications::$channel = $channel;

        $result = TestOrderServiceWithPaymentNotifications::handleTerminalPayPush(
            7,
            '10.00',
            PayOrder::TYPE_WECHAT,
            'evt-payment-success',
            ['terminalCode' => 'T7']
        );

        self::assertTrue($result['matched']);
        self::assertFalse($result['notifyOk']);
        self::assertCount(1, $channel->messages);
        self::assertStringContainsString('cloud-order-push', $channel->messages[0]);
        self::assertStringContainsString('商户回调: 失败', $channel->messages[0]);
    }

    public function test_heartbeat_marks_offline_terminal_online_and_can_notify_recovery(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_terminal_recovered' => '1',
        ]);

        $now = time();
        \think\facade\Db::name('monitor_terminal')->insert([
            'terminal_code' => 'T2',
            'terminal_name' => 'Terminal 2',
            'online_state' => 'offline',
            'monitor_key' => 'monitor-key',
            'last_heartbeat_at' => $now - 300,
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.1',
            'created_at' => $now - 3600,
            'updated_at' => $now - 300,
        ]);
        $terminalId = (int) \think\facade\Db::name('monitor_terminal')
            ->where('terminal_code', 'T2')
            ->value('id');

        TestMonitorServiceWithNotifications::$channel = new FakeTelegramChannel();
        TestMonitorServiceWithNotifications::heartbeatForTerminal($terminalId, '127.0.0.2');

        self::assertSame(
            'online',
            \think\facade\Db::name('monitor_terminal')->where('id', $terminalId)->value('online_state')
        );
        self::assertCount(1, TestMonitorServiceWithNotifications::$channel->messages);
        self::assertStringContainsString('恢复在线', TestMonitorServiceWithNotifications::$channel->messages[0]);
    }

    public function test_timeout_check_notifies_when_online_terminal_becomes_offline(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_terminal_offline' => '1',
        ]);

        $now = time();
        Db::name('monitor_terminal')->insert([
            'terminal_code' => 'T3',
            'terminal_name' => 'Terminal 3',
            'online_state' => 'online',
            'monitor_key' => 'monitor-key-3',
            'last_heartbeat_at' => $now - 300,
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.1',
            'created_at' => $now - 3600,
            'updated_at' => $now - 300,
        ]);

        TestMonitorServiceWithNotifications::$channel = new FakeTelegramChannel();
        $affected = TestMonitorServiceWithNotifications::checkMonitorTimeout();

        self::assertSame(1, $affected);
        self::assertSame(
            'offline',
            Db::name('monitor_terminal')->where('terminal_code', 'T3')->value('online_state')
        );
        self::assertCount(1, TestMonitorServiceWithNotifications::$channel->messages);
        self::assertStringContainsString('监控端离线', TestMonitorServiceWithNotifications::$channel->messages[0]);
        self::assertStringContainsString('T3', TestMonitorServiceWithNotifications::$channel->messages[0]);
    }

    public function test_timeout_check_respects_terminal_offline_event_switch(): void
    {
        $this->seedSettings([
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_terminal_offline' => '0',
        ]);

        $now = time();
        Db::name('monitor_terminal')->insert([
            'terminal_code' => 'T4',
            'terminal_name' => 'Terminal 4',
            'online_state' => 'online',
            'monitor_key' => 'monitor-key-4',
            'last_heartbeat_at' => $now - 300,
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.1',
            'created_at' => $now - 3600,
            'updated_at' => $now - 300,
        ]);

        TestMonitorServiceWithNotifications::$channel = new FakeTelegramChannel();
        $affected = TestMonitorServiceWithNotifications::checkMonitorTimeout();

        self::assertSame(1, $affected);
        self::assertSame([], TestMonitorServiceWithNotifications::$channel->messages);
    }

    public function test_close_expired_orders_notifies_when_orders_are_closed(): void
    {
        $this->seedSettings([
            'close' => '30',
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_expired_order_cleanup' => '1',
        ]);

        $now = time();
        Db::name('pay_order')->insert([
            'create_date' => $now - 3600,
            'notify_url' => 'https://merchant.example/notify',
            'order_id' => 'expired-notify-order',
            'pay_id' => 'merchant-expired-notify',
            'price' => '10.00',
            'really_price' => '10.00',
            'return_url' => 'https://merchant.example/return',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_WECHAT,
        ]);

        TestMonitorServiceWithNotifications::$channel = new FakeTelegramChannel();
        $affected = TestMonitorServiceWithNotifications::closeExpiredOrders(true, false);

        self::assertSame(1, $affected);
        self::assertSame(
            PayOrder::STATE_EXPIRED,
            (int) Db::name('pay_order')->where('order_id', 'expired-notify-order')->value('state')
        );
        self::assertCount(1, TestMonitorServiceWithNotifications::$channel->messages);
        self::assertStringContainsString('关闭过期订单', TestMonitorServiceWithNotifications::$channel->messages[0]);
        self::assertStringContainsString('1', TestMonitorServiceWithNotifications::$channel->messages[0]);
    }

    public function test_close_expired_orders_respects_expired_order_event_switch(): void
    {
        $this->seedSettings([
            'close' => '30',
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_expired_order_cleanup' => '0',
        ]);

        $now = time();
        Db::name('pay_order')->insert([
            'create_date' => $now - 3600,
            'notify_url' => 'https://merchant.example/notify',
            'order_id' => 'expired-muted-order',
            'pay_id' => 'merchant-expired-muted',
            'price' => '10.00',
            'really_price' => '10.00',
            'return_url' => 'https://merchant.example/return',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_WECHAT,
        ]);

        TestMonitorServiceWithNotifications::$channel = new FakeTelegramChannel();
        $affected = TestMonitorServiceWithNotifications::closeExpiredOrders(true, false);

        self::assertSame(1, $affected);
        self::assertSame([], TestMonitorServiceWithNotifications::$channel->messages);
    }
}

final class TestNotificationService extends NotificationService
{
    public function __construct(private readonly FakeTelegramChannel $fakeChannel)
    {
    }

    protected function telegramChannel(): TelegramNotificationChannel
    {
        return $this->fakeChannel;
    }
}

class FakeTelegramChannel extends TelegramNotificationChannel
{
    /** @var array<int, string> */
    public array $messages = [];

    public function send(string $message): bool
    {
        $this->messages[] = $message;

        return true;
    }
}

final class TestMonitorServiceWithNotifications extends \app\service\MonitorService
{
    public static FakeTelegramChannel $channel;

    protected static function notificationService(): NotificationService
    {
        return new TestNotificationService(self::$channel);
    }
}

final class TestOrderServiceWithPaymentNotifications extends OrderService
{
    public static FakeTelegramChannel $channel;

    protected static function notificationService(): NotificationService
    {
        return new TestNotificationService(self::$channel);
    }
}
