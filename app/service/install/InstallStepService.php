<?php
declare(strict_types=1);

namespace app\service\install;

use PDO;

class InstallStepService
{
    /**
     * @param array{
     *   env: array<string, string>,
     *   admin_user: string,
     *   admin_pass: string
     * } $payload
     * @return array{
     *   env: array{written: bool, path: string, content: string},
     *   installed: bool,
     *   status: string,
     *   admin_user: string
     * }
     */
    public function install(array $payload): array
    {
        $env = $this->envWriter()->write($payload['env']);
        $pdo = $this->connect($payload['env']);

        $this->databaseBootstrap()->importBootstrapSql($pdo);
        // vmq.sql disables autocommit during bootstrap; restore it before persisting install metadata.
        $pdo->exec('SET AUTOCOMMIT = 1');
        $this->adminBootstrap()->bootstrap([
            'admin_user' => $payload['admin_user'],
            'admin_pass' => $payload['admin_pass'],
            'schema_version' => (string) config('app.ver'),
            'app_version' => (string) config('app.ver'),
            'install_status' => $env['written'] ? 'installed' : 'pending',
        ], $pdo);

        return [
            'env' => $env,
            'installed' => $env['written'],
            'status' => $env['written'] ? 'installed' : 'pending',
            'admin_user' => trim($payload['admin_user']),
        ];
    }

    /**
     * @param array<string, string> $env
     */
    protected function connect(array $env): PDO
    {
        $type = $env['DB_TYPE'] ?? 'mysql';
        if ($type !== 'mysql') {
            throw new \RuntimeException('当前安装器仅支持 MySQL');
        }

        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '3306';
        $database = $env['DB_NAME'] ?? '';
        $charset = $env['DB_CHARSET'] ?? 'utf8mb4';
        $user = $env['DB_USER'] ?? '';
        $pass = $env['DB_PASS'] ?? '';

        return new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    protected function envWriter(): EnvWriter
    {
        return new EnvWriter();
    }

    protected function databaseBootstrap(): DatabaseBootstrapService
    {
        return new DatabaseBootstrapService();
    }

    protected function adminBootstrap(): AdminBootstrapService
    {
        return new AdminBootstrapService();
    }
}
