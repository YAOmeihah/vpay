<?php
declare(strict_types=1);

namespace app\service\config;

use app\model\Setting;

class SettingConfigRepository
{
    /**
     * @var array<int, string>
     */
    private const CONFIG_KEYS = [
        'user',
        'pass',
        'notifyUrl',
        'returnUrl',
        'key',
        'close',
        'payQf',
        'allocationStrategy',
        'notify_ssl_verify',
        'maintenance_enabled',
        'maintenance_token',
        'maintenance_allowed_ips',
        'maintenance_task_terminal_offline_check',
        'maintenance_task_expired_order_cleanup',
        'maintenance_last_run_at',
        'maintenance_last_run_result',
        'notify_telegram_enabled',
        'notify_telegram_bot_token',
        'notify_telegram_chat_id',
        'notify_event_terminal_offline',
        'notify_event_terminal_recovered',
        'notify_event_expired_order_cleanup',
        'notify_event_maintenance_exception',
    ];

    public function get(string $key, string $default = ''): string
    {
        if (!$this->isConfigKey($key)) {
            return $default;
        }

        return Setting::getConfigValue($key, $default);
    }

    public function set(string $key, string $value): bool
    {
        if (!$this->isConfigKey($key)) {
            return false;
        }

        return Setting::setConfigValue($key, $value);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $values = [];

        foreach (self::CONFIG_KEYS as $key) {
            $values[$key] = Setting::getConfigValue($key);
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return self::CONFIG_KEYS;
    }

    private function isConfigKey(string $key): bool
    {
        return in_array($key, self::CONFIG_KEYS, true);
    }
}
