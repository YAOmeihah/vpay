<?php
declare(strict_types=1);

namespace app\service\runtime;

use app\model\Setting;

class SettingMonitorState implements MonitorState
{
    public function getLastHeartbeatAt(): int
    {
        return (int) Setting::getConfigValue('lastheart');
    }

    public function getLastPaidAt(): int
    {
        return (int) Setting::getConfigValue('lastpay');
    }

    public function markHeartbeatAt(int $timestamp): void
    {
        Setting::setConfigValue('lastheart', (string) $timestamp);
    }

    public function markPaidAt(int $timestamp): void
    {
        Setting::setConfigValue('lastpay', (string) $timestamp);
    }

    public function markOnline(): void
    {
        Setting::setConfigValue('jkstate', '1');
    }

    public function markOffline(): void
    {
        Setting::setConfigValue('jkstate', '0');
    }

    public function isOnline(): bool
    {
        return Setting::getConfigValue('jkstate') === '1';
    }
}
