<?php
declare(strict_types=1);

namespace app\service\admin;

use app\service\CacheService;
use app\service\config\SettingConfigRepository;

class AdminSettingsService
{
    private const ALLOCATION_STRATEGIES = ['fixed_priority', 'round_robin'];

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
            'close' => $this->getConfigValue('close'),
            'payQf' => $this->getConfigValue('payQf'),
            'allocationStrategy' => $this->getConfigValue('allocationStrategy', 'fixed_priority'),
        ];

        $settings['key'] = $this->ensureGeneratedKey('key', $settings['key']);

        return $settings;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveSettings(array $input): void
    {
        $params = [
            'user', 'pass', 'notifyUrl', 'returnUrl', 'key',
            'notify_ssl_verify', 'close', 'payQf', 'allocationStrategy',
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
                'notify_ssl_verify', 'close', 'payQf', 'allocationStrategy',
            ], true)) {
                $value = trim($value);
            }

            if ($param === 'allocationStrategy' && !in_array($value, self::ALLOCATION_STRATEGIES, true)) {
                throw new \RuntimeException('分配策略无效');
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
        return $this->configRepository()->get($key, $default);
    }

    protected function setConfigValue(string $key, string $value): bool
    {
        return $this->configRepository()->set($key, $value);
    }

    protected function generateKey(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return md5(uniqid((string) mt_rand(), true));
        }
    }

    private function ensureGeneratedKey(string $settingKey, string $currentValue): string
    {
        if (!empty($currentValue)) {
            return $currentValue;
        }

        $generated = $this->generateKey();
        $this->setConfigValue($settingKey, $generated);

        return $generated;
    }

    protected function dashboardStatsService(): DashboardStatsService
    {
        return new DashboardStatsService();
    }

    protected function configRepository(): SettingConfigRepository
    {
        return new SettingConfigRepository();
    }
}
