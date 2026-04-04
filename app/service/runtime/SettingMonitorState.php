<?php
declare(strict_types=1);

namespace app\service\runtime;

class SettingMonitorState implements MonitorState
{
    public function getLastHeartbeatAt(): int
    {
        return (int) $this->getLastHeartbeatRaw();
    }

    public function getLastPaidAt(): int
    {
        return (int) $this->getLastPaidRaw();
    }

    public function getLastHeartbeatRaw(): string
    {
        return $this->getConfigValue('lastheart');
    }

    public function getLastPaidRaw(): string
    {
        return $this->getConfigValue('lastpay');
    }

    public function getOnlineFlagRaw(): string
    {
        return $this->getConfigValue('jkstate');
    }

    public function markHeartbeatAt(int $timestamp): void
    {
        $this->setConfigValue('lastheart', (string) $timestamp);
    }

    public function markPaidAt(int $timestamp): void
    {
        $this->setConfigValue('lastpay', (string) $timestamp);
    }

    public function markOnline(): void
    {
        $this->setConfigValue('jkstate', '1');
    }

    public function markOffline(): void
    {
        $this->setConfigValue('jkstate', '0');
    }

    public function isOnline(): bool
    {
        return $this->getOnlineFlagRaw() === '1';
    }

    protected function getConfigValue(string $key, string $default = ''): string
    {
        return (new SettingStateRepository())->get($key, $default);
    }

    protected function setConfigValue(string $key, string $value): bool
    {
        return (new SettingStateRepository())->set($key, $value);
    }
}
