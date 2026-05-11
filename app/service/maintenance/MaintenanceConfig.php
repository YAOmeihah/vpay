<?php
declare(strict_types=1);

namespace app\service\maintenance;

use app\service\config\SettingConfigRepository;

class MaintenanceConfig
{
    public function isEnabled(): bool
    {
        return $this->setting('maintenance_enabled', '0') === '1';
    }

    public function token(): string
    {
        return $this->setting('maintenance_token');
    }

    /**
     * @return array<int, string>
     */
    public function allowedIps(): array
    {
        return $this->splitList($this->setting('maintenance_allowed_ips'));
    }

    public function taskEnabled(string $task): bool
    {
        return match ($task) {
            'terminal_offline_check' => $this->setting('maintenance_task_terminal_offline_check', '1') === '1',
            'expired_order_cleanup' => $this->setting('maintenance_task_expired_order_cleanup', '1') === '1',
            default => false,
        };
    }

    public function telegramEnabled(): bool
    {
        return $this->setting('notify_telegram_enabled', '0') === '1';
    }

    public function telegramBotToken(): string
    {
        return $this->setting('notify_telegram_bot_token');
    }

    public function telegramChatId(): string
    {
        return $this->setting('notify_telegram_chat_id');
    }

    public function eventEnabled(string $event): bool
    {
        return match ($event) {
            'terminal_offline' => $this->setting('notify_event_terminal_offline', '1') === '1',
            'terminal_recovered' => $this->setting('notify_event_terminal_recovered', '1') === '1',
            'expired_order_cleanup' => $this->setting('notify_event_expired_order_cleanup', '1') === '1',
            'maintenance_exception' => $this->setting('notify_event_maintenance_exception', '1') === '1',
            'payment_success' => $this->setting('notify_event_payment_success', '1') === '1',
            default => false,
        };
    }

    public function paymentSuccessCallbackStatusEnabled(): bool
    {
        return $this->setting('notify_payment_success_callback_status', '1') === '1';
    }

    private function setting(string $key, string $default = ''): string
    {
        return $this->repository()->get($key, $default);
    }

    /**
     * @return array<int, string>
     */
    private function splitList(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            preg_split('/[,\r\n]+/', $value) ?: []
        )));
    }

    private function repository(): SettingConfigRepository
    {
        return app()->make(SettingConfigRepository::class);
    }
}
