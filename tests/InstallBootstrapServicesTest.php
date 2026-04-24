<?php
declare(strict_types=1);

namespace tests;

use app\model\Setting;
use app\service\install\AdminBootstrapService;
use app\service\install\DatabaseBootstrapService;
use app\service\install\EnvWriter;
use app\service\install\InstallStepService;
use PDO;

final class InstallBootstrapServicesTest extends TestCase
{
    public function test_admin_bootstrap_overwrites_placeholder_admin_and_generates_sign_key(): void
    {
        Setting::setConfigValue('user', 'admin');
        Setting::setConfigValue('pass', '$2y$10$placeholder');
        Setting::setConfigValue('key', '');

        $service = new AdminBootstrapService();
        $service->bootstrap([
            'admin_user' => 'owner',
            'admin_pass' => 'owner-password-123',
            'schema_version' => '2.1.0',
            'app_version' => '2.1.0',
        ]);

        self::assertSame('owner', Setting::getConfigValue('user'));
        self::assertTrue(password_verify('owner-password-123', Setting::getConfigValue('pass')));
        self::assertNotSame('', Setting::getConfigValue('key'));
        self::assertSame('installed', Setting::getConfigValue('install_status'));
        self::assertSame('2.1.0', Setting::getConfigValue('schema_version'));
        self::assertSame('2.1.0', Setting::getConfigValue('app_version'));
    }

    public function test_env_writer_returns_manual_copy_payload_when_target_is_not_writable(): void
    {
        $writer = new class extends EnvWriter {
            protected function writeTarget(string $path, string $content): bool
            {
                return false;
            }
        };

        $result = $writer->write([
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'vmqphp8',
        ]);

        self::assertFalse($result['written']);
        self::assertStringContainsString('DB_HOST = 127.0.0.1', $result['content']);
        self::assertStringContainsString('DB_NAME = vmqphp8', $result['content']);
    }

    public function test_database_bootstrap_split_statements_skips_bom_prefixed_comment_lines(): void
    {
        $service = new class extends DatabaseBootstrapService {
            /**
             * @return list<string>
             */
            public function parse(string $sql): array
            {
                return $this->splitStatements($sql);
            }
        };

        $statements = $service->parse("\xEF\xBB\xBF-- phpMyAdmin SQL Dump\n-- comment\nSET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");

        self::assertSame([
            'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";',
        ], $statements);
    }

    public function test_database_bootstrap_split_statements_treats_utf8_comment_lines_as_single_lines(): void
    {
        $service = new class extends DatabaseBootstrapService {
            /**
             * @return list<string>
             */
            public function parse(string $sql): array
            {
                return $this->splitStatements($sql);
            }
        };

        $statements = $service->parse("-- ThinkPHP 8 支付系统数据库结构 (完全兼容原始vmq.sql)\nSET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");

        self::assertSame([
            'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";',
        ], $statements);
    }

    public function test_install_step_service_commits_admin_bootstrap_after_bootstrap_sql_disables_autocommit(): void
    {
        $databaseConfig = $this->app->config->get('database');
        $connection = $databaseConfig['connections']['mysql'];
        $host = (string) $connection['hostname'];
        $port = (string) $connection['hostport'];
        $database = (string) $connection['database'];
        $user = (string) $connection['username'];
        $pass = (string) $connection['password'];
        $charset = (string) $connection['charset'];

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $service = new class($pdo) extends InstallStepService {
            public function __construct(private readonly PDO $pdo)
            {
            }

            protected function connect(array $env): PDO
            {
                return $this->pdo;
            }

            protected function envWriter(): EnvWriter
            {
                return new class extends EnvWriter {
                    public function write(array $values): array
                    {
                        return [
                            'written' => true,
                            'path' => '/tmp/.env',
                            'content' => 'APP_DEBUG = false',
                        ];
                    }
                };
            }

            protected function databaseBootstrap(): DatabaseBootstrapService
            {
                return new class extends DatabaseBootstrapService {
                    public function importBootstrapSql(PDO $pdo): void
                    {
                        $pdo->exec('DROP TABLE IF EXISTS `setting`');
                        $pdo->exec('CREATE TABLE `setting` (`vkey` VARCHAR(100) NOT NULL, `vvalue` TEXT NULL, PRIMARY KEY (`vkey`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
                        $pdo->exec("INSERT INTO `setting` (`vkey`, `vvalue`) VALUES ('user', ''), ('pass', ''), ('key', ''), ('install_status', 'pending'), ('schema_version', '2.1.0'), ('app_version', '2.1.0'), ('notify_ssl_verify', '1')");
                        $pdo->exec('SET AUTOCOMMIT = 0');
                        $pdo->exec('COMMIT');
                    }
                };
            }
        };

        $service->install([
            'env' => [
                'DB_TYPE' => 'mysql',
                'DB_HOST' => $host,
                'DB_NAME' => $database,
                'DB_USER' => $user,
                'DB_PASS' => $pass,
                'DB_PORT' => $port,
                'DB_CHARSET' => $charset,
            ],
            'admin_user' => 'owner',
            'admin_pass' => 'owner-password-123',
        ]);

        $verificationPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $rows = $verificationPdo
            ->query("SELECT `vkey`, `vvalue` FROM `setting` WHERE `vkey` IN ('user', 'key', 'install_status')")
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        self::assertSame('owner', $rows['user']);
        self::assertSame('installed', $rows['install_status']);
        self::assertNotSame('', $rows['key']);
    }
}
