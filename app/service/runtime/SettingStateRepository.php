<?php
declare(strict_types=1);

namespace app\service\runtime;

use app\model\Setting;

class SettingStateRepository
{
    /**
     * @var array<int, string>
     */
    private const STATE_KEYS = [
        'lastheart',
        'lastpay',
        'jkstate',
    ];

    public function get(string $key, string $default = ''): string
    {
        if (!$this->isStateKey($key)) {
            return $default;
        }

        return Setting::getConfigValue($key, $default);
    }

    public function set(string $key, string $value): bool
    {
        if (!$this->isStateKey($key)) {
            return false;
        }

        return Setting::setConfigValue($key, $value);
    }

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'lastheart' => Setting::getConfigValue('lastheart'),
            'lastpay' => Setting::getConfigValue('lastpay'),
            'jkstate' => Setting::getConfigValue('jkstate'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return self::STATE_KEYS;
    }

    private function isStateKey(string $key): bool
    {
        return in_array($key, self::STATE_KEYS, true);
    }
}
