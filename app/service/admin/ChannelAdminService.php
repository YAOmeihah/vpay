<?php
declare(strict_types=1);

namespace app\service\admin;

use app\model\TerminalChannel;

class ChannelAdminService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForTerminal(int $terminalId): array
    {
        $rows = TerminalChannel::where('terminal_id', $terminalId)
            ->select()
            ->toArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) ($row['type'] ?? 0)] = $row;
        }

        return [
            $this->buildSlot($terminalId, 1, $indexed[1] ?? null),
            $this->buildSlot($terminalId, 2, $indexed[2] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $timestamp = time();
        $data = [
            'terminal_id' => (int) ($input['terminalId'] ?? $input['terminal_id'] ?? 0),
            'type' => (int) ($input['type'] ?? 0),
            'channel_name' => trim((string) ($input['channelName'] ?? $input['channel_name'] ?? '')),
            'status' => trim((string) ($input['status'] ?? 'enabled')) ?: 'enabled',
            'pay_url' => trim((string) ($input['payUrl'] ?? $input['pay_url'] ?? '')),
            'updated_at' => $timestamp,
        ];

        if ($data['terminal_id'] <= 0 || $data['type'] <= 0) {
            throw new \RuntimeException('支付配置参数不完整');
        }

        if ($data['channel_name'] === '') {
            $data['channel_name'] = $this->defaultChannelName($data['type']);
        }

        if ($id > 0) {
            TerminalChannel::where('id', $id)->update($data);
            return TerminalChannel::where('id', $id)->findOrFail()->toArray();
        }

        $existing = TerminalChannel::where('terminal_id', $data['terminal_id'])
            ->where('type', $data['type'])
            ->find();

        if ($existing) {
            TerminalChannel::where('id', $existing['id'])->update($data);
            return TerminalChannel::where('id', $existing['id'])->findOrFail()->toArray();
        }

        $data['created_at'] = $timestamp;
        $channel = TerminalChannel::create($data);
        return $channel->toArray();
    }

    public function toggle(int $id): void
    {
        $channel = TerminalChannel::where('id', $id)->findOrFail();
        $next = (string) $channel['status'] === 'enabled' ? 'disabled' : 'enabled';

        TerminalChannel::where('id', $id)->update([
            'status' => $next,
            'updated_at' => time(),
        ]);
    }

    /**
     * @param array<string, mixed>|null $channel
     * @return array<string, mixed>
     */
    private function buildSlot(int $terminalId, int $type, ?array $channel): array
    {
        $meta = $this->slotMeta($type);
        if ($channel !== null) {
            $channel['exists'] = true;
            $channel['slot_key'] = $meta['slot_key'];
            $channel['slot_label'] = $meta['slot_label'];

            return $channel;
        }

        return [
            'id' => null,
            'terminal_id' => $terminalId,
            'type' => $type,
            'channel_name' => $meta['default_channel_name'],
            'status' => 'disabled',
            'pay_url' => '',
            'last_used_at' => 0,
            'created_at' => 0,
            'updated_at' => 0,
            'exists' => false,
            'slot_key' => $meta['slot_key'],
            'slot_label' => $meta['slot_label'],
        ];
    }

    private function defaultChannelName(int $type): string
    {
        return $this->slotMeta($type)['default_channel_name'];
    }

    /**
     * @return array{slot_key: string, slot_label: string, default_channel_name: string}
     */
    private function slotMeta(int $type): array
    {
        return match ($type) {
            1 => [
                'slot_key' => 'wechat',
                'slot_label' => '微信',
                'default_channel_name' => '微信收款',
            ],
            2 => [
                'slot_key' => 'alipay',
                'slot_label' => '支付宝',
                'default_channel_name' => '支付宝收款',
            ],
            default => [
                'slot_key' => 'unknown',
                'slot_label' => '未知',
                'default_channel_name' => '支付配置',
            ],
        };
    }
}
