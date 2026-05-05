<?php
declare(strict_types=1);

namespace app\service\notification;

use app\service\maintenance\MaintenanceConfig;
use Throwable;

class NotificationService
{
    /**
     * @param array<string, mixed> $terminal
     */
    public function terminalOffline(array $terminal): bool
    {
        if (!$this->config()->eventEnabled('terminal_offline')) {
            return false;
        }

        return $this->send('监控端离线: ' . $this->terminalLabel($terminal));
    }

    /**
     * @param array<string, mixed> $terminal
     */
    public function terminalRecovered(array $terminal): bool
    {
        if (!$this->config()->eventEnabled('terminal_recovered')) {
            return false;
        }

        return $this->send('监控端恢复在线: ' . $this->terminalLabel($terminal));
    }

    public function expiredOrdersClosed(int $count): bool
    {
        if ($count <= 0 || !$this->config()->eventEnabled('expired_order_cleanup')) {
            return false;
        }

        return $this->send('维护任务已关闭过期订单: ' . $count . ' 笔');
    }

    public function maintenanceException(Throwable $exception): bool
    {
        if (!$this->config()->eventEnabled('maintenance_exception')) {
            return false;
        }

        return $this->send('维护任务异常: ' . $exception->getMessage());
    }

    public function sendTestMessage(): bool
    {
        return $this->send('维护通知测试: Telegram 配置可用');
    }

    protected function send(string $message): bool
    {
        if (!$this->telegramReady()) {
            return false;
        }

        return $this->telegramChannel()->send($message);
    }

    /**
     * @param array<string, mixed> $terminal
     */
    private function terminalLabel(array $terminal): string
    {
        $code = (string) ($terminal['terminal_code'] ?? '');
        $name = (string) ($terminal['terminal_name'] ?? '');

        return trim($name . ($code !== '' ? ' [' . $code . ']' : ''));
    }

    private function telegramReady(): bool
    {
        $config = $this->config();

        return $config->telegramEnabled()
            && $config->telegramBotToken() !== ''
            && $config->telegramChatId() !== '';
    }

    protected function telegramChannel(): TelegramNotificationChannel
    {
        return app()->make(TelegramNotificationChannel::class);
    }

    private function config(): MaintenanceConfig
    {
        return app()->make(MaintenanceConfig::class);
    }
}
