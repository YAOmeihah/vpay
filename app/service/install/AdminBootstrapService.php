<?php
declare(strict_types=1);

namespace app\service\install;

use app\model\Setting;
use PDO;

class AdminBootstrapService
{
    /**
     * @param array<string, string> $payload
     */
    public function bootstrap(array $payload, ?PDO $pdo = null): void
    {
        $this->persistValue('user', trim((string) ($payload['admin_user'] ?? '')), $pdo);
        $this->persistValue('pass', password_hash((string) ($payload['admin_pass'] ?? ''), PASSWORD_DEFAULT), $pdo);
        $this->persistValue('key', $this->generateKey(), $pdo);
        $this->persistValue('notify_ssl_verify', $this->currentNotifySslVerify($pdo), $pdo);
        $this->persistValue('install_status', (string) ($payload['install_status'] ?? 'installed'), $pdo);
        $this->persistValue('schema_version', (string) ($payload['schema_version'] ?? ''), $pdo);
        $this->persistValue('app_version', (string) ($payload['app_version'] ?? ''), $pdo);
        $this->persistValue('install_time', (string) time(), $pdo);
        $this->persistValue('install_guid', bin2hex(random_bytes(16)), $pdo);
    }

    protected function generateKey(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return md5(uniqid((string) mt_rand(), true));
        }
    }

    private function currentNotifySslVerify(?PDO $pdo): string
    {
        if ($pdo === null) {
            return Setting::getConfigValue('notify_ssl_verify', '1') ?: '1';
        }

        $statement = $pdo->prepare('SELECT `vvalue` FROM `setting` WHERE `vkey` = :key LIMIT 1');
        $statement->execute(['key' => 'notify_ssl_verify']);
        $value = $statement->fetchColumn();

        return $value !== false && trim((string) $value) !== '' ? (string) $value : '1';
    }

    private function persistValue(string $key, string $value, ?PDO $pdo): void
    {
        if ($pdo === null) {
            Setting::setConfigValue($key, $value);
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO `setting` (`vkey`, `vvalue`) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE `vvalue` = VALUES(`vvalue`)'
        );
        $statement->execute([
            'key' => $key,
            'value' => $value,
        ]);
    }
}
