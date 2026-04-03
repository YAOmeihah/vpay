<?php
declare(strict_types=1);

namespace app\service\config;

use app\model\Setting;

class SettingSystemConfig implements SystemConfig
{
    public function getNotifyUrl(): string
    {
        return Setting::getConfigValue('notifyUrl');
    }

    public function getReturnUrl(): string
    {
        return Setting::getConfigValue('returnUrl');
    }

    public function getSignKey(): string
    {
        return Setting::getConfigValue('key');
    }

    public function getOrderCloseMinutes(): int
    {
        return (int) Setting::getConfigValue('close');
    }

    public function getPayQfMode(): string
    {
        return Setting::getConfigValue('payQf');
    }

    public function getWeChatPayUrl(): string
    {
        return Setting::getConfigValue('wxpay');
    }

    public function getAlipayPayUrl(): string
    {
        return Setting::getConfigValue('zfbpay');
    }

    public function getNotifySslVerifyEnabled(): bool
    {
        return Setting::getConfigValue('notify_ssl_verify', '1') === '1';
    }

    public function getEpayConfig(): array
    {
        return [
            'enabled' => Setting::getConfigValue('epay_enabled', '0') === '1',
            'pid' => trim(Setting::getConfigValue('epay_pid')),
            'key' => trim(Setting::getConfigValue('epay_key')),
            'name' => trim(Setting::getConfigValue('epay_name', '订单支付')),
            'private_key' => trim(Setting::getConfigValue('epay_private_key')),
            'public_key' => trim(Setting::getConfigValue('epay_public_key')),
        ];
    }
}
