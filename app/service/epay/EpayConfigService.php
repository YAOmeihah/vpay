<?php
declare(strict_types=1);

namespace app\service\epay;

use app\service\config\SettingSystemConfig;
use app\service\config\SystemConfig;

class EpayConfigService
{
    public static function getConfig(): array
    {
        return static::systemConfig()->getEpayConfig();
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

    public static function requireEnabledConfigV2(): array
    {
        $config = static::getConfig();

        if (!$config['enabled']) {
            throw new \RuntimeException('EPay 通道未启用');
        }

        if ($config['pid'] === '') {
            throw new \RuntimeException('EPay 配置不完整');
        }

        if ($config['private_key'] === '' || $config['public_key'] === '') {
            throw new \RuntimeException('EPay RSA 密钥未配置');
        }

        return $config;
    }

    protected static function systemConfig(): SystemConfig
    {
        return new SettingSystemConfig();
    }
}
