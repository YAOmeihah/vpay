<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MultiTerminalSchemaSqlTest extends TestCase
{
    public function test_schema_script_describes_terminal_tables_and_indexes(): void
    {
        $path = __DIR__ . '/../docs/sql/2026-04-23-multi-terminal-schema.sql';

        self::assertFileExists($path);

        $sql = file_get_contents($path);
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE `monitor_terminal`', $sql);
        self::assertStringContainsString('CREATE TABLE `terminal_channel`', $sql);
        self::assertStringContainsString('CREATE TABLE `payment_event`', $sql);
        self::assertStringContainsString('ALTER TABLE `tmp_price`', $sql);
        self::assertStringContainsString('ADD UNIQUE KEY `uniq_channel_price` (`channel_id`,`price`)', $sql);
        self::assertStringContainsString('ALTER TABLE `pay_qrcode`', $sql);
        self::assertStringContainsString('ADD COLUMN `channel_id` bigint(20) DEFAULT NULL', $sql);
    }

    public function test_bootstrap_schema_and_models_expose_multi_terminal_fields(): void
    {
        $bootstrapSql = file_get_contents(__DIR__ . '/../vmq.sql');
        self::assertIsString($bootstrapSql);
        self::assertStringContainsString('CREATE TABLE `monitor_terminal`', $bootstrapSql);
        self::assertStringContainsString('CREATE TABLE `terminal_channel`', $bootstrapSql);
        self::assertStringContainsString('CREATE TABLE `payment_event`', $bootstrapSql);
        self::assertStringContainsString('`dispatch_priority` int(11) NOT NULL DEFAULT 100', $bootstrapSql);
        self::assertStringContainsString('`terminal_id` bigint(20) DEFAULT NULL', $bootstrapSql);
        self::assertStringContainsString('`channel_id` bigint(20) DEFAULT NULL', $bootstrapSql);
        self::assertStringContainsString('ADD UNIQUE KEY `uniq_channel_price` (`channel_id`,`price`)', $bootstrapSql);
        self::assertStringContainsString('ADD INDEX `idx_type_status_terminal` (`type`,`status`,`terminal_id`)', $bootstrapSql);
        self::assertStringContainsString("('notify_ssl_verify', '1')", $bootstrapSql);
        self::assertStringContainsString("('install_status', 'pending')", $bootstrapSql);
        self::assertStringContainsString("('schema_version', '2.1.0')", $bootstrapSql);
        self::assertStringContainsString("('app_version', '2.1.0')", $bootstrapSql);
        self::assertStringNotContainsString("('user', 'admin')", $bootstrapSql);
        self::assertStringNotContainsString('`priority` int(11) NOT NULL DEFAULT 100', $bootstrapSql);
        self::assertStringNotContainsString('idx_type_status_priority', $bootstrapSql);

        $payOrderSource = file_get_contents(__DIR__ . '/../app/model/PayOrder.php');
        self::assertIsString($payOrderSource);
        self::assertStringContainsString("'terminal_id' => 'bigint'", $payOrderSource);
        self::assertStringContainsString("'channel_id'  => 'bigint'", $payOrderSource);
        self::assertStringContainsString("'assign_status' => 'string'", $payOrderSource);
        self::assertStringContainsString("'assign_reason' => 'string'", $payOrderSource);
        self::assertStringContainsString("'terminal_snapshot' => 'string'", $payOrderSource);
        self::assertStringContainsString("'channel_snapshot' => 'string'", $payOrderSource);

        $tmpPriceSource = file_get_contents(__DIR__ . '/../app/model/TmpPrice.php');
        self::assertIsString($tmpPriceSource);
        self::assertStringContainsString("'channel_id' => 'bigint'", $tmpPriceSource);
        self::assertStringContainsString("protected \$pk = 'oid';", $tmpPriceSource);

        $payQrcodeSource = file_get_contents(__DIR__ . '/../app/model/PayQrcode.php');
        self::assertIsString($payQrcodeSource);
        self::assertStringContainsString("'channel_id' => 'bigint'", $payQrcodeSource);
    }
}
