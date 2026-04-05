<?php
declare(strict_types=1);

namespace app\service\config;

class SettingSystemConfig implements SystemConfig
{
    public function getNotifyUrl(): string
    {
        return $this->getConfigValue('notifyUrl');
    }

    public function getReturnUrl(): string
    {
        return $this->getConfigValue('returnUrl');
    }

    public function getSignKey(): string
    {
        return $this->getConfigValue('key');
    }

    public function getOrderCloseMinutes(): int
    {
        return (int) $this->getConfigValue('close');
    }

    public function getOrderCloseRaw(): string
    {
        return $this->getConfigValue('close');
    }

    public function getPayQfMode(): string
    {
        return $this->getConfigValue('payQf');
    }

    public function getWeChatPayUrl(): string
    {
        return $this->getConfigValue('wxpay');
    }

    public function getAlipayPayUrl(): string
    {
        return $this->getConfigValue('zfbpay');
    }

    public function getNotifySslVerifyEnabled(): bool
    {
        return $this->getConfigValue('notify_ssl_verify', '1') === '1';
    }

    protected function getConfigValue(string $key, string $default = ''): string
    {
        return (new SettingConfigRepository())->get($key, $default);
    }
}
