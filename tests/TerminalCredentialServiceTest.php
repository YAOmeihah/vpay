<?php
declare(strict_types=1);

use app\model\MonitorTerminal;
use app\service\terminal\TerminalCredentialService;
use PHPUnit\Framework\TestCase;

final class TerminalCredentialServiceTest extends TestCase
{
    public function test_it_uses_terminal_specific_key_when_terminal_code_is_present(): void
    {
        $terminal = new MonitorTerminal([
            'terminal_code' => 'term-a',
            'monitor_key' => 'terminal-secret',
        ]);

        $service = new TerminalCredentialService(
            lookupByCode: static fn (string $code) => $code === 'term-a' ? $terminal : null,
            legacyDefaultCode: 'legacy-default',
        );

        self::assertSame('terminal-secret', $service->requireKeyFor('term-a'));
    }

    public function test_it_falls_back_to_legacy_default_when_terminal_code_is_missing(): void
    {
        $legacy = new MonitorTerminal([
            'terminal_code' => 'legacy-default',
            'monitor_key' => 'legacy-secret',
        ]);

        $service = new TerminalCredentialService(
            lookupByCode: static fn (string $code) => $code === 'legacy-default' ? $legacy : null,
            legacyDefaultCode: 'legacy-default',
        );

        self::assertSame('legacy-secret', $service->requireKeyFor(''));
    }
}
