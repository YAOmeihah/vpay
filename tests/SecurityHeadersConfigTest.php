<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

final class SecurityHeadersConfigTest extends TestCase
{
    public function test_csp_script_src_does_not_allow_unsafe_inline(): void
    {
        $config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'security.php';
        $csp = (string) ($config['headers']['Content-Security-Policy'] ?? '');

        self::assertStringContainsString('script-src', $csp);
        self::assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }
}
