<?php
declare(strict_types=1);

namespace app\service\terminal;

use app\model\MonitorTerminal;

/**
 * 终端身份与监控密钥解析服务
 */
class TerminalCredentialService
{
    /**
     * @param null|\Closure(string): ?MonitorTerminal $lookupByCode
     */
    public function __construct(
        private readonly ?\Closure $lookupByCode = null,
        private readonly string $legacyDefaultCode = 'legacy-default',
    ) {
    }

    public function requireTerminal(string $terminalCode): MonitorTerminal
    {
        $normalized = trim($terminalCode) !== '' ? trim($terminalCode) : $this->legacyDefaultCode;

        $lookup = $this->lookupByCode
            ?? static fn (string $code): ?MonitorTerminal => MonitorTerminal::where('terminal_code', $code)->find();

        $terminal = $lookup($normalized);
        if (!$terminal instanceof MonitorTerminal) {
            throw new \RuntimeException('监控终端不存在');
        }

        return $terminal;
    }

    public function requireKeyFor(string $terminalCode): string
    {
        return (string) $this->requireTerminal($terminalCode)['monitor_key'];
    }
}
