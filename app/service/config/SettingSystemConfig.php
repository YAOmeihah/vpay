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

    public function getCloseMinutes(): int
    {
        return (int) Setting::getConfigValue('close');
    }

    public function getPayQfMode(): int
    {
        return (int) Setting::getConfigValue('payQf');
    }

    public function getWeChatPayUrl(): string
    {
        return Setting::getConfigValue('wxpay');
    }

    public function getAlipayPayUrl(): string
    {
        return Setting::getConfigValue('zfbpay');
    }

    public function shouldVerifyNotifySsl(): bool
    {
        return Setting::getConfigValue('notify_ssl_verify', '1') === '1';
    }

    public function getEpayConfig(): array
    {
        return [
            'enabled' => Setting::getConfigValue('epay_enabled', '0') === '1',
            'pid' => Setting::getConfigValue('epay_pid'),
            'key' => Setting::getConfigValue('epay_key'),
            'name' => Setting::getConfigValue('epay_name', '订单支付'),
            'private_key' => Setting::getConfigValue('epay_private_key'),
            'public_key' => Setting::getConfigValue('epay_public_key'),
        ];
    }
}
