<?php
declare(strict_types=1);

namespace app\service\admin;

use app\service\CacheService;
use app\service\config\SettingConfigRepository;
use app\service\security\KeyEncryptionService;

class AdminSettingsService
{
    private const ALLOCATION_STRATEGIES = ['fixed_priority', 'round_robin'];
    private const BOOLEAN_SETTINGS = [
        'notify_ssl_verify',
        'maintenance_enabled',
        'maintenance_task_terminal_offline_check',
        'maintenance_task_expired_order_cleanup',
        'notify_telegram_enabled',
        'notify_event_terminal_offline',
        'notify_event_terminal_recovered',
        'notify_event_expired_order_cleanup',
        'notify_event_maintenance_exception',
    ];

    /**
     * @return array<string, string>
     */
    public function getSettings(): array
    {
        $settings = [
            'user'               => $this->getConfigValue('user'),
            'pass'               => '',
            'notifyUrl'          => $this->getConfigValue('notifyUrl'),
            'returnUrl'          => $this->getConfigValue('returnUrl'),
            'key'                => $this->getDecryptedSignKey(),
            'notify_ssl_verify'  => $this->getConfigValue('notify_ssl_verify', '1'),
            'close'              => $this->getConfigValue('close'),
            'payQf'              => $this->getConfigValue('payQf'),
            'allocationStrategy' => $this->getConfigValue('allocationStrategy', 'fixed_priority'),
            'maintenance_enabled' => $this->getConfigValue('maintenance_enabled', '0'),
            'maintenance_token' => $this->getConfigValue('maintenance_token'),
            'maintenance_allowed_ips' => $this->getConfigValue('maintenance_allowed_ips'),
            'maintenance_task_terminal_offline_check' => $this->getConfigValue('maintenance_task_terminal_offline_check', '1'),
            'maintenance_task_expired_order_cleanup' => $this->getConfigValue('maintenance_task_expired_order_cleanup', '1'),
            'maintenance_last_run_at' => $this->getConfigValue('maintenance_last_run_at'),
            'maintenance_last_run_result' => $this->getConfigValue('maintenance_last_run_result'),
            'notify_telegram_enabled' => $this->getConfigValue('notify_telegram_enabled', '0'),
            'notify_telegram_bot_token' => $this->getConfigValue('notify_telegram_bot_token'),
            'notify_telegram_chat_id' => $this->getConfigValue('notify_telegram_chat_id'),
            'notify_event_terminal_offline' => $this->getConfigValue('notify_event_terminal_offline', '1'),
            'notify_event_terminal_recovered' => $this->getConfigValue('notify_event_terminal_recovered', '1'),
            'notify_event_expired_order_cleanup' => $this->getConfigValue('notify_event_expired_order_cleanup', '1'),
            'notify_event_maintenance_exception' => $this->getConfigValue('notify_event_maintenance_exception', '1'),
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
            'maintenance_enabled', 'maintenance_token', 'maintenance_allowed_ips',
            'maintenance_task_terminal_offline_check', 'maintenance_task_expired_order_cleanup',
            'notify_telegram_enabled', 'notify_telegram_bot_token', 'notify_telegram_chat_id',
            'notify_event_terminal_offline', 'notify_event_terminal_recovered',
            'notify_event_expired_order_cleanup', 'notify_event_maintenance_exception',
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

            if (in_array($param, self::BOOLEAN_SETTINGS, true)) {
                $value = $this->normalizeBoolean($value);
            } elseif ($param === 'maintenance_allowed_ips') {
                $value = $this->normalizeIpList($value);
            } elseif (in_array($param, [
                'user', 'notifyUrl', 'returnUrl', 'key',
                'notify_ssl_verify', 'close', 'payQf', 'allocationStrategy',
                'maintenance_token', 'notify_telegram_bot_token', 'notify_telegram_chat_id',
            ], true)) {
                $value = trim($value);
            }

            if ($param === 'allocationStrategy' && !in_array($value, self::ALLOCATION_STRATEGIES, true)) {
                throw new \RuntimeException('分配策略无效');
            }

            if ($param === 'key' && $value !== '') {
                $value = $this->keyEncryptionService()->encrypt($value);
            }

            $this->setConfigValue($param, $value);
        }

        $this->dashboardStatsService()->clearStats();
    }

    public function generateMaintenanceToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->setConfigValue('maintenance_token', $token);

        return $token;
    }

    public function getAdminUsername(): string
    {
        return $this->getConfigValue('user');
    }

    public function getAdminPasswordHash(): string
    {
        return $this->getConfigValue('pass');
    }

    public function getDecryptedSignKey(): string
    {
        $raw = $this->getConfigValue('key');
        if ($raw === '') {
            return '';
        }

        return $this->keyEncryptionService()->decrypt($raw);
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
        } catch (\Throwable $e) {
            throw new \RuntimeException('安全随机数生成失败，无法生成密钥', 0, $e);
        }
    }

    private function normalizeBoolean(mixed $value): string
    {
        return in_array((string) $value, ['1', 'true', 'on'], true) ? '1' : '0';
    }

    private function normalizeIpList(mixed $value): string
    {
        $items = preg_split('/[,\r\n]+/', (string) $value) ?: [];
        $items = array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            $items
        )));

        return implode(',', $items);
    }

    private function ensureGeneratedKey(string $settingKey, string $currentValue): string
    {
        if (!empty($currentValue)) {
            return $currentValue;
        }

        $generated = $this->generateKey();
        $this->setConfigValue($settingKey, $this->keyEncryptionService()->encrypt($generated));

        return $generated;
    }

    protected function keyEncryptionService(): KeyEncryptionService
    {
        return app()->make(KeyEncryptionService::class);
    }

    protected function dashboardStatsService(): DashboardStatsService
    {
        return app()->make(DashboardStatsService::class);
    }

    protected function configRepository(): SettingConfigRepository
    {
        return app()->make(SettingConfigRepository::class);
    }
}
