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
        );

        self::assertSame('terminal-secret', $service->requireKeyFor('term-a'));
    }

    public function test_it_rejects_missing_terminal_code(): void
    {
        $service = new TerminalCredentialService(
            lookupByCode: static fn (string $code) => null,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('终端编码不能为空');

        $service->requireKeyFor('');
    }
}
