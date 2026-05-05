<?php
declare(strict_types=1);

namespace tests;

use app\service\maintenance\MaintenanceConfig;

final class MaintenanceConfigTest extends TestCase
{
    public function test_defaults_keep_endpoint_disabled_and_tasks_enabled(): void
    {
        $config = new MaintenanceConfig();

        self::assertFalse($config->isEnabled());
        self::assertSame('', $config->token());
        self::assertSame([], $config->allowedIps());
        self::assertTrue($config->taskEnabled('terminal_offline_check'));
        self::assertTrue($config->taskEnabled('expired_order_cleanup'));
        self::assertFalse($config->telegramEnabled());
        self::assertTrue($config->eventEnabled('terminal_offline'));
        self::assertTrue($config->eventEnabled('terminal_recovered'));
        self::assertTrue($config->eventEnabled('expired_order_cleanup'));
        self::assertTrue($config->eventEnabled('maintenance_exception'));
    }

    public function test_normalizes_saved_database_values(): void
    {
        $this->seedSettings([
            'maintenance_enabled' => '1',
            'maintenance_token' => 'token',
            'maintenance_allowed_ips' => "127.0.0.1, 10.0.0.2\n,",
            'maintenance_task_terminal_offline_check' => '0',
            'notify_telegram_enabled' => '1',
            'notify_telegram_bot_token' => 'bot',
            'notify_telegram_chat_id' => 'chat',
            'notify_event_terminal_recovered' => '0',
        ]);

        $config = new MaintenanceConfig();

        self::assertTrue($config->isEnabled());
        self::assertSame('token', $config->token());
        self::assertSame(['127.0.0.1', '10.0.0.2'], $config->allowedIps());
        self::assertFalse($config->taskEnabled('terminal_offline_check'));
        self::assertTrue($config->taskEnabled('expired_order_cleanup'));
        self::assertTrue($config->telegramEnabled());
        self::assertSame('bot', $config->telegramBotToken());
        self::assertSame('chat', $config->telegramChatId());
        self::assertFalse($config->eventEnabled('terminal_recovered'));
    }
}
