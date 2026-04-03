<?php
declare(strict_types=1);

namespace app\service\admin;

use app\model\Setting;
use app\service\CacheService;

class AdminSettingsService
{
    /**
     * @return array<string, string>
     */
    public function getSettings(): array
    {
        $settings = [
            'user' => $this->getConfigValue('user'),
            'pass' => '',
            'notifyUrl' => $this->getConfigValue('notifyUrl'),
            'returnUrl' => $this->getConfigValue('returnUrl'),
            'key' => $this->getConfigValue('key'),
            'lastheart' => $this->getConfigValue('lastheart'),
            'lastpay' => $this->getConfigValue('lastpay'),
            'jkstate' => $this->getConfigValue('jkstate'),
            'close' => $this->getConfigValue('close'),
            'payQf' => $this->getConfigValue('payQf'),
            'wxpay' => $this->getConfigValue('wxpay'),
            'zfbpay' => $this->getConfigValue('zfbpay'),
            'epay_enabled' => $this->getConfigValue('epay_enabled', '0'),
            'epay_pid' => $this->getConfigValue('epay_pid'),
            'epay_key' => '',
            'epay_name' => $this->getConfigValue('epay_name', '订单支付'),
            'epay_private_key' => '',
            'epay_public_key' => $this->getConfigValue('epay_public_key'),
        ];

        if ($settings['key'] === '') {
            $settings['key'] = $this->generateKey();
            $this->setConfigValue('key', $settings['key']);
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveSettings(array $input): void
    {
        $params = [
            'user', 'pass', 'notifyUrl', 'returnUrl', 'key',
            'close', 'payQf', 'wxpay', 'zfbpay',
            'epay_enabled', 'epay_pid', 'epay_key', 'epay_name',
            'epay_private_key', 'epay_public_key',
        ];

        foreach ($params as $param) {
            $value = $input[$param] ?? '';

            if ($param === 'pass') {
                if ($value === '' || $value === null) {
                    continue;
                }

                $value = password_hash((string) $value, PASSWORD_DEFAULT);
            }

            if (in_array($param, ['epay_key', 'epay_private_key', 'epay_public_key'], true)) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
            }

            if ($param === 'epay_enabled') {
                $value = (string) (($value === '1' || $value === 1) ? '1' : '0');
            }

            if (in_array($param, ['epay_pid', 'epay_name'], true)) {
                $value = trim((string) $value);
            }

            $this->setConfigValue($param, (string) $value);
        }

        $this->dashboardStatsService()->clearStats();
    }

    public function getAdminUsername(): string
    {
        return $this->getConfigValue('user');
    }

    public function getAdminPasswordHash(): string
    {
        return $this->getConfigValue('pass');
    }

    public function warmSettingsCache(): int
    {
        $settings = $this->allSettings();
        $count = 0;

        foreach ($settings as $setting) {
            $key = (string) ($setting['vkey'] ?? '');
            if ($key === '') {
                continue;
            }

            if ($this->cacheSetting($key, (string) ($setting['vvalue'] ?? ''))) {
                $count++;
            }
        }

        return $count;
    }

    protected function getConfigValue(string $key, string $default = ''): string
    {
        return Setting::getConfigValue($key, $default);
    }

    protected function setConfigValue(string $key, string $value): bool
    {
        return Setting::setConfigValue($key, $value);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function allSettings(): array
    {
        return Setting::select()->toArray();
    }

    protected function cacheSetting(string $key, string $value): bool
    {
        return CacheService::cacheSetting($key, $value);
    }

    protected function generateKey(): string
    {
        return md5((string) time());
    }

    protected function dashboardStatsService(): DashboardStatsService
    {
        return new DashboardStatsService();
    }
}
