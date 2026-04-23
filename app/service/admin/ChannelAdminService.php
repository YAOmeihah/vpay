<?php
declare(strict_types=1);

namespace app\service\admin;

use app\model\MonitorTerminal;
use app\model\TerminalChannel;
use app\service\config\SettingConfigRepository;

class ChannelAdminService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForTerminal(int $terminalId): array
    {
        return TerminalChannel::where('terminal_id', $terminalId)
            ->order('id', 'desc')
            ->select()
            ->toArray();
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
            'priority' => (int) ($input['priority'] ?? 100),
            'updated_at' => $timestamp,
        ];

        if ($data['terminal_id'] <= 0 || $data['type'] <= 0 || $data['channel_name'] === '') {
            throw new \RuntimeException('通道参数不完整');
        }

        if ($id > 0) {
            TerminalChannel::where('id', $id)->update($data);
            return TerminalChannel::where('id', $id)->findOrFail()->toArray();
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
     * @return array{wxpay: string, zfbpay: string}
     */
    public function legacyDefaultPair(): array
    {
        $wxpay = $this->getConfigValue('wxpay');
        $zfbpay = $this->getConfigValue('zfbpay');

        try {
            $terminal = MonitorTerminal::where('terminal_code', 'legacy-default')->find();
            if ($terminal) {
                $wx = TerminalChannel::where('terminal_id', $terminal['id'])->where('type', 1)->find();
                $zfb = TerminalChannel::where('terminal_id', $terminal['id'])->where('type', 2)->find();

                if ($wx) {
                    $wxpay = (string) $wx['pay_url'];
                }
                if ($zfb) {
                    $zfbpay = (string) $zfb['pay_url'];
                }
            }
        } catch (\Throwable) {
            // Legacy tests may not have multi-terminal tables yet.
        }

        return [
            'wxpay' => $wxpay,
            'zfbpay' => $zfbpay,
        ];
    }

    public function updateLegacyDefaultPayUrl(int $type, string $payUrl): void
    {
        try {
            $terminal = MonitorTerminal::where('terminal_code', 'legacy-default')->find();
            if ($terminal) {
                $channel = TerminalChannel::where('terminal_id', $terminal['id'])->where('type', $type)->find();
                if ($channel) {
                    TerminalChannel::where('id', $channel['id'])->update([
                        'pay_url' => $payUrl,
                        'updated_at' => time(),
                    ]);
                }
            }
        } catch (\Throwable) {
            // Fall back to the legacy config storage when terminal tables are unavailable.
        }

        $this->configRepository()->set($type === 1 ? 'wxpay' : 'zfbpay', $payUrl);
    }

    protected function getConfigValue(string $key, string $default = ''): string
    {
        return $this->configRepository()->get($key, $default);
    }

    protected function configRepository(): SettingConfigRepository
    {
        return new SettingConfigRepository();
    }
}
