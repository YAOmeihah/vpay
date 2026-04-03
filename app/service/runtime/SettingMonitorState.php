<?php
declare(strict_types=1);

namespace app\service\runtime;

use app\model\Setting;

class SettingMonitorState implements MonitorState
{
    public function getLastHeartbeat(): int
    {
        return (int) Setting::getConfigValue('lastheart');
    }

    public function setLastHeartbeat(int $timestamp): void
    {
        Setting::setConfigValue('lastheart', (string) $timestamp);
    }

    public function getLastPayTime(): int
    {
        return (int) Setting::getConfigValue('lastpay');
    }

    public function setLastPayTime(int $timestamp): void
    {
        Setting::setConfigValue('lastpay', (string) $timestamp);
    }

    public function isOnline(): bool
    {
        return Setting::getConfigValue('jkstate') === '1';
    }

    public function setOnline(bool $online): void
    {
        Setting::setConfigValue('jkstate', $online ? '1' : '0');
    }
}
