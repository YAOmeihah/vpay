<?php
declare(strict_types=1);

namespace app\service\admin;

use app\service\CacheService;
use app\service\config\SettingConfigRepository;
use app\service\runtime\SettingStateRepository;

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
            'notify_ssl_verify' => $this->getConfigValue('notify_ssl_verify', '1'),
            'lastheart' => $this->getConfigValue('lastheart'),
            'lastpay' => $this->getConfigValue('lastpay'),
            'jkstate' => $this->getConfigValue('jkstate'),
            'close' => $this->getConfigValue('close'),
            'payQf' => $this->getConfigValue('payQf'),
            'wxpay' => $this->getConfigValue('wxpay'),
            'zfbpay' => $this->getConfigValue('zfbpay'),
        ];

        if (empty($settings['key'])) {
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
            'notify_ssl_verify', 'close', 'payQf', 'wxpay', 'zfbpay',
        ];

        foreach ($params as $param) {
            if (!array_key_exists($param, $input)) {
                continue;
            }

            $value = $input[$param];

            if ($param === 'pass') {
                $value = trim((string) $value);
                if ($value === '' || $value === '0') {
                    continue;
                }

                $value = password_hash($value, PASSWORD_DEFAULT);
            }

            $value = (string) $value;

            if (in_array($param, [
                'user', 'notifyUrl', 'returnUrl', 'key',
                'notify_ssl_verify', 'close', 'payQf', 'wxpay', 'zfbpay',
            ], true)) {
                $value = trim($value);
            }

            $this->setConfigValue($param, $value);
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
        $settings = $this->configRepository()->all();
        $count = 0;

        foreach ($settings as $key => $value) {
            if ($this->cacheSetting((string) $key, (string) $value)) {
                $count++;
            }
        }

        return $count;
    }

    protected function cacheSetting(string $key, string $value): bool
    {
        return CacheService::cacheSetting($key, $value);
    }

    protected function getConfigValue(string $key, string $default = ''): string
    {
        if ($this->isStateKey($key)) {
            return $this->stateRepository()->get($key, $default);
        }

        return $this->configRepository()->get($key, $default);
    }

    protected function setConfigValue(string $key, string $value): bool
    {
        if ($this->isStateKey($key)) {
            return $this->stateRepository()->set($key, $value);
        }

        return $this->configRepository()->set($key, $value);
    }

    protected function generateKey(): string
    {
        return md5((string) time());
    }

    protected function dashboardStatsService(): DashboardStatsService
    {
        return new DashboardStatsService();
    }

    protected function configRepository(): SettingConfigRepository
    {
        return new SettingConfigRepository();
    }

    protected function stateRepository(): SettingStateRepository
    {
        return new SettingStateRepository();
    }

    private function isStateKey(string $key): bool
    {
        return in_array($key, $this->stateRepository()->keys(), true);
    }
}
