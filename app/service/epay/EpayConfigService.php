<?php
declare(strict_types=1);

namespace app\service\epay;

use app\model\Setting;

class EpayConfigService
{
    public static function getConfig(): array
    {
        return [
            'enabled' => Setting::getConfigValue('epay_enabled', '0') === '1',
            'pid' => trim(Setting::getConfigValue('epay_pid')),
            'key' => trim(Setting::getConfigValue('epay_key')),
            'name' => trim(Setting::getConfigValue('epay_name', '订单支付')),
        ];
    }

    public static function requireEnabledConfig(): array
    {
        $config = static::getConfig();

        if (!$config['enabled']) {
            throw new \RuntimeException('EPay 通道未启用');
        }

        if ($config['pid'] === '' || $config['key'] === '') {
            throw new \RuntimeException('EPay 配置不完整');
        }

        return $config;
    }
}
