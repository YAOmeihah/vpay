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
        'monitorKey',
        'close',
        'payQf',
        'wxpay',
        'zfbpay',
        'notify_ssl_verify',
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
