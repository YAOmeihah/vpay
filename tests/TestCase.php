<?php
declare(strict_types=1);

namespace tests;

use app\model\MonitorTerminal;
use app\model\PayQrcode;
use app\model\Setting;
use app\model\TerminalChannel;
use app\service\CacheService;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use think\App;

abstract class TestCase extends BaseTestCase
{
    protected App $app;
    protected static App $sharedApp;

    protected static string $rootPath;
    protected static string $testDatabase;
    protected static array $envConfig;
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        self::$envConfig = self::loadTestEnv();
        self::$testDatabase = (self::$envConfig['DB_NAME'] ?? 'vmqphp8') . '_codex_test';

        self::recreateDatabase();

        self::$sharedApp = new App(self::$rootPath);
        self::$sharedApp->initialize();
    }

    public static function tearDownAfterClass(): void
    {
        self::dropDatabase();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = self::$sharedApp;
        $this->configureDatabase();
        $this->configureCache();
        $this->resetTables();
        $this->seedBaseSettings();
    }

    protected function tearDown(): void
    {
        CacheService::clearAll();
        parent::tearDown();
    }

    protected function seedSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::setConfigValue((string) $key, (string) $value);
        }
    }

    protected function insertQrcode(int $type, float $price, string $payUrl, ?int $channelId = null): void
    {
        PayQrcode::create([
            'channel_id' => $channelId ?? ($type === 1 ? 1 : 2),
            'type' => $type,
            'price' => $price,
            'pay_url' => $payUrl,
        ]);
    }

    private function configureDatabase(): void
    {
        $databaseConfig = $this->app->config->get('database');
        $databaseConfig['default'] = 'mysql';
        $databaseConfig['connections']['mysql']['type'] = self::$envConfig['DB_TYPE'] ?? 'mysql';
        $databaseConfig['connections']['mysql']['hostname'] = self::$envConfig['DB_HOST'] ?? '127.0.0.1';
        $databaseConfig['connections']['mysql']['database'] = self::$testDatabase;
        $databaseConfig['connections']['mysql']['username'] = self::$envConfig['DB_USER'] ?? 'root';
        $databaseConfig['connections']['mysql']['password'] = self::$envConfig['DB_PASS'] ?? '';
        $databaseConfig['connections']['mysql']['hostport'] = self::$envConfig['DB_PORT'] ?? '3306';
        $databaseConfig['connections']['mysql']['charset'] = self::$envConfig['DB_CHARSET'] ?? 'utf8mb4';
        $databaseConfig['connections']['mysql']['trigger_sql'] = false;

        $this->app->config->set($databaseConfig, 'database');
    }

    private function configureCache(): void
    {
        $cachePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'vpay-phpunit-cache-'
            . substr(sha1(self::$rootPath), 0, 12)
            . DIRECTORY_SEPARATOR;
        if (!is_dir($cachePath) && !@mkdir($cachePath, 0777, true) && !is_dir($cachePath)) {
            throw new \RuntimeException('Failed to create PHPUnit cache directory: ' . $cachePath);
        }

        $cacheConfig = $this->app->config->get('cache');
        $cacheConfig['default'] = 'file';
        $cacheConfig['stores']['file']['path'] = $cachePath;

        $this->app->config->set($cacheConfig, 'cache');
        CacheService::clearAll();
    }

    private function resetTables(): void
    {
        $pdo = self::connectToTestDatabase();

        $pdo->exec('DROP TABLE IF EXISTS `pay_order`');
        $pdo->exec('DROP TABLE IF EXISTS `pay_qrcode`');
        $pdo->exec('DROP TABLE IF EXISTS `tmp_price`');
        $pdo->exec('DROP TABLE IF EXISTS `payment_event`');
        $pdo->exec('DROP TABLE IF EXISTS `terminal_channel`');
        $pdo->exec('DROP TABLE IF EXISTS `monitor_terminal`');
        $pdo->exec('DROP TABLE IF EXISTS `setting`');

        $pdo->exec(
            'CREATE TABLE `pay_order` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `close_date` BIGINT NOT NULL DEFAULT 0,
                `create_date` BIGINT NOT NULL DEFAULT 0,
                `is_auto` INT NOT NULL DEFAULT 0,
                `notify_url` VARCHAR(1000) NOT NULL DEFAULT \'\',
                `order_id` VARCHAR(100) NOT NULL DEFAULT \'\',
                `param` VARCHAR(255) NOT NULL DEFAULT \'\',
                `pay_date` BIGINT NOT NULL DEFAULT 0,
                `pay_id` VARCHAR(100) NOT NULL DEFAULT \'\',
                `pay_url` VARCHAR(1000) NOT NULL DEFAULT \'\',
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `really_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `return_url` VARCHAR(1000) NOT NULL DEFAULT \'\',
                `terminal_id` BIGINT NULL DEFAULT NULL,
                `channel_id` BIGINT NULL DEFAULT NULL,
                `assign_status` VARCHAR(32) NOT NULL DEFAULT \'assigned\',
                `assign_reason` VARCHAR(255) NOT NULL DEFAULT \'\',
                `terminal_snapshot` VARCHAR(255) NOT NULL DEFAULT \'\',
                `channel_snapshot` VARCHAR(255) NOT NULL DEFAULT \'\',
                `state` INT NOT NULL DEFAULT 0,
                `type` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_pay_id` (`pay_id`),
                UNIQUE KEY `uniq_order_id` (`order_id`),
                KEY `idx_create_date_state` (`create_date`, `state`),
                KEY `idx_really_price_state_type` (`really_price`, `state`, `type`),
                KEY `idx_terminal_type_state_price` (`terminal_id`, `type`, `state`, `really_price`),
                KEY `idx_channel_state` (`channel_id`, `state`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE `pay_qrcode` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `channel_id` BIGINT NULL DEFAULT NULL,
                `pay_url` VARCHAR(1000) NOT NULL DEFAULT \'\',
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `type` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_type_price` (`type`, `price`),
                KEY `idx_channel_price` (`channel_id`, `price`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE `tmp_price` (
                `price` VARCHAR(64) NOT NULL,
                `channel_id` BIGINT NULL DEFAULT NULL,
                `oid` VARCHAR(100) NOT NULL DEFAULT \'\',
                PRIMARY KEY (`oid`),
                UNIQUE KEY `uniq_channel_price` (`channel_id`, `price`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE `monitor_terminal` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `terminal_code` VARCHAR(64) NOT NULL,
                `terminal_name` VARCHAR(128) NOT NULL,
                `dispatch_priority` INT NOT NULL DEFAULT 100,
                `status` VARCHAR(32) NOT NULL DEFAULT \'enabled\',
                `online_state` VARCHAR(32) NOT NULL DEFAULT \'offline\',
                `monitor_key` VARCHAR(128) NOT NULL DEFAULT \'\',
                `last_heartbeat_at` BIGINT NOT NULL DEFAULT 0,
                `last_paid_at` BIGINT NOT NULL DEFAULT 0,
                `last_ip` VARCHAR(64) NOT NULL DEFAULT \'\',
                `device_meta` TEXT NULL,
                `created_at` BIGINT NOT NULL DEFAULT 0,
                `updated_at` BIGINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_terminal_code` (`terminal_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE `terminal_channel` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `terminal_id` BIGINT NOT NULL,
                `type` INT NOT NULL,
                `channel_name` VARCHAR(128) NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT \'enabled\',
                `pay_url` VARCHAR(1000) NOT NULL DEFAULT \'\',
                `last_used_at` BIGINT NOT NULL DEFAULT 0,
                `created_at` BIGINT NOT NULL DEFAULT 0,
                `updated_at` BIGINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_terminal_type` (`terminal_id`, `type`),
                KEY `idx_type_status_terminal` (`type`, `status`, `terminal_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE `payment_event` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `terminal_id` BIGINT NOT NULL,
                `channel_id` BIGINT NULL DEFAULT NULL,
                `event_id` VARCHAR(128) NOT NULL,
                `type` INT NOT NULL,
                `amount_cents` INT NOT NULL,
                `raw_payload` TEXT NOT NULL,
                `matched_order_id` VARCHAR(255) NOT NULL DEFAULT \'\',
                `result` VARCHAR(32) NOT NULL,
                `created_at` BIGINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_terminal_event` (`terminal_id`, `event_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE `setting` (
                `vkey` VARCHAR(100) NOT NULL,
                `vvalue` TEXT NULL,
                PRIMARY KEY (`vkey`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        CacheService::clearAll();
    }

    private function seedBaseSettings(): void
    {
        $this->seedSettings([
            'payQf' => '0',
            'allocationStrategy' => 'fixed_priority',
            'close' => '15',
            'notifyUrl' => 'https://merchant.example/notify',
            'returnUrl' => 'https://merchant.example/return',
            'key' => 'native-key',
        ]);

        MonitorTerminal::create([
            'id' => 1,
            'terminal_code' => 'default-terminal',
            'terminal_name' => '默认终端',
            'dispatch_priority' => 10,
            'status' => 'enabled',
            'online_state' => 'online',
            'monitor_key' => 'native-monitor-key',
            'last_heartbeat_at' => time(),
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.1',
            'device_meta' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        TerminalChannel::create([
            'id' => 1,
            'terminal_id' => 1,
            'type' => 1,
            'channel_name' => '默认微信通道',
            'status' => 'enabled',
            'pay_url' => 'weixin://default-pay-url',
            'last_used_at' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        TerminalChannel::create([
            'id' => 2,
            'terminal_id' => 1,
            'type' => 2,
            'channel_name' => '默认支付宝通道',
            'status' => 'enabled',
            'pay_url' => 'alipays://default-pay-url',
            'last_used_at' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    private static function recreateDatabase(): void
    {
        $pdo = self::connectToServer();
        $database = str_replace('`', '``', self::$testDatabase);

        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
        $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private static function dropDatabase(): void
    {
        $pdo = self::connectToServer();
        $database = str_replace('`', '``', self::$testDatabase);
        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
    }

    private static function connectToServer(): PDO
    {
        $host = self::$envConfig['DB_HOST'] ?? '127.0.0.1';
        $port = self::$envConfig['DB_PORT'] ?? '3306';
        $charset = self::$envConfig['DB_CHARSET'] ?? 'utf8mb4';
        $user = self::$envConfig['DB_USER'] ?? 'root';
        $pass = self::$envConfig['DB_PASS'] ?? '';

        return new PDO(
            "mysql:host={$host};port={$port};charset={$charset}",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    private static function connectToTestDatabase(): PDO
    {
        $host = self::$envConfig['DB_HOST'] ?? '127.0.0.1';
        $port = self::$envConfig['DB_PORT'] ?? '3306';
        $charset = self::$envConfig['DB_CHARSET'] ?? 'utf8mb4';
        $user = self::$envConfig['DB_USER'] ?? 'root';
        $pass = self::$envConfig['DB_PASS'] ?? '';

        return new PDO(
            "mysql:host={$host};port={$port};dbname=" . self::$testDatabase . ";charset={$charset}",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    private static function loadTestEnv(): array
    {
        $files = [
            self::$rootPath . '.env',
            self::$rootPath . '.env.testing',
        ];

        $map = [
            'DB_TYPE' => 'VMQ_TEST_DB_TYPE',
            'DB_HOST' => 'VMQ_TEST_DB_HOST',
            'DB_PORT' => 'VMQ_TEST_DB_PORT',
            'DB_NAME' => 'VMQ_TEST_DB_NAME',
            'DB_USER' => 'VMQ_TEST_DB_USER',
            'DB_PASS' => 'VMQ_TEST_DB_PASS',
            'DB_CHARSET' => 'VMQ_TEST_DB_CHARSET',
        ];

        return TestEnvResolver::resolve(
            $files,
            $map,
            static function (string $path) {
                if (!is_file($path)) {
                    return null;
                }

                $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
                return is_array($parsed) ? $parsed : null;
            },
            static fn (string $envVar) => getenv($envVar)
        );
    }
}
