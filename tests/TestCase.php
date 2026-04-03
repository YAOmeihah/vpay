<?php
declare(strict_types=1);

namespace tests;

use app\model\PayQrcode;
use app\model\Setting;
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
    protected static string $privateKeyPem;
    protected static string $publicKeyPem;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        self::$envConfig = parse_ini_file(self::$rootPath . '.env', false, INI_SCANNER_RAW) ?: [];
        self::$testDatabase = (self::$envConfig['DB_NAME'] ?? 'vmqphp8') . '_codex_test';

        self::generateRsaKeys();
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

    protected function insertQrcode(int $type, float $price, string $payUrl): void
    {
        PayQrcode::create([
            'type' => $type,
            'price' => $price,
            'pay_url' => $payUrl,
        ]);
    }

    protected function getPrivateKeyPem(): string
    {
        return self::$privateKeyPem;
    }

    protected function getPublicKeyPem(): string
    {
        return self::$publicKeyPem;
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
        $cachePath = self::$rootPath . 'runtime' . DIRECTORY_SEPARATOR . 'phpunit-cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
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
                `state` INT NOT NULL DEFAULT 0,
                `type` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE `pay_qrcode` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `pay_url` VARCHAR(1000) NOT NULL DEFAULT \'\',
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `type` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE `tmp_price` (
                `price` VARCHAR(64) NOT NULL,
                `oid` VARCHAR(100) NOT NULL DEFAULT \'\',
                PRIMARY KEY (`price`)
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
            'jkstate' => '1',
            'payQf' => '0',
            'close' => '15',
            'notifyUrl' => 'https://merchant.example/notify',
            'returnUrl' => 'https://merchant.example/return',
            'wxpay' => 'weixin://default-pay-url',
            'zfbpay' => 'alipays://default-pay-url',
            'key' => 'native-key',
            'epay_enabled' => '1',
            'epay_pid' => '10001',
            'epay_key' => 'epay-md5-key',
            'epay_name' => '订单支付',
            'epay_private_key' => self::$privateKeyPem,
            'epay_public_key' => self::$publicKeyPem,
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

    private static function generateRsaKeys(): void
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $opensslConfig = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        if (is_file($opensslConfig)) {
            $config['config'] = $opensslConfig;
        }

        $resource = openssl_pkey_new($config);

        if ($resource === false) {
            throw new \RuntimeException('无法生成测试 RSA 密钥');
        }

        openssl_pkey_export($resource, $privateKeyPem, null, $config);
        $publicKeyDetails = openssl_pkey_get_details($resource);

        self::$privateKeyPem = $privateKeyPem;
        self::$publicKeyPem = (string) ($publicKeyDetails['key'] ?? '');
    }
}
