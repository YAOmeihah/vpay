<?php
declare(strict_types=1);

namespace app\service\terminal;

/**
 * 终端通道分配策略
 */
class TerminalAllocatorService
{
    /**
     * @param array<int, array<string, mixed>> $channels
     * @return array<string, mixed>
     */
    public function pickChannel(string $strategy, array $channels, int $type, ?int $lastChannelId = null): array
    {
        $eligible = array_values(array_filter(
            $channels,
            static fn (array $channel): bool => (int) ($channel['type'] ?? 0) === $type
                && (string) ($channel['status'] ?? '') === 'enabled'
                && (string) ($channel['terminal_status'] ?? '') === 'enabled'
                && (string) ($channel['online_state'] ?? '') === 'online'
        ));

        if ($eligible === []) {
            throw new \RuntimeException($type === 1 ? '当前无可用微信收款终端' : '当前无可用支付宝收款终端');
        }

        usort($eligible, static fn (array $left, array $right): int => [
            (int) ($left['dispatch_priority'] ?? 0),
            (int) ($left['terminal_id'] ?? 0),
            (int) ($left['id'] ?? 0),
        ] <=> [
            (int) ($right['dispatch_priority'] ?? 0),
            (int) ($right['terminal_id'] ?? 0),
            (int) ($right['id'] ?? 0),
        ]);

        if ($strategy !== 'round_robin' || $lastChannelId === null) {
            return $eligible[0];
        }

        foreach ($eligible as $index => $channel) {
            if ((int) ($channel['id'] ?? 0) === $lastChannelId) {
                return $eligible[($index + 1) % count($eligible)];
            }
        }

        return $eligible[0];
    }
}
