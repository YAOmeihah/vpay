<?php
declare(strict_types=1);

namespace app\service\admin;

use app\model\MonitorTerminal;
use app\service\config\SettingConfigRepository;

class TerminalAdminService
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function paginate(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? 10));
        $keyword = trim((string) ($params['keyword'] ?? ''));

        $query = MonitorTerminal::order('id', 'desc');
        if ($keyword !== '') {
            $query->where(function ($inner) use ($keyword): void {
                $inner->whereLike('terminal_code', '%' . $keyword . '%')
                    ->whereOrLike('terminal_name', '%' . $keyword . '%');
            });
        }

        $count = $query->count();
        $rows = $query->page($page, $limit)->select()->toArray();

        return [
            'data' => $rows,
            'count' => $count,
            'page' => $page,
            'limit' => $limit,
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
            'terminal_code' => trim((string) ($input['terminalCode'] ?? $input['terminal_code'] ?? '')),
            'terminal_name' => trim((string) ($input['terminalName'] ?? $input['terminal_name'] ?? '')),
            'status' => trim((string) ($input['status'] ?? 'enabled')) ?: 'enabled',
            'online_state' => trim((string) ($input['online_state'] ?? 'offline')) ?: 'offline',
            'monitor_key' => trim((string) ($input['monitorKey'] ?? $input['monitor_key'] ?? '')),
            'last_ip' => trim((string) ($input['lastIp'] ?? $input['last_ip'] ?? '')),
            'device_meta' => $this->normalizeDeviceMeta($input['deviceMeta'] ?? $input['device_meta'] ?? null),
            'updated_at' => $timestamp,
        ];

        if ($data['terminal_code'] === '' || $data['terminal_name'] === '') {
            throw new \RuntimeException('终端编码和终端名称不能为空');
        }

        if ($data['monitor_key'] === '') {
            $data['monitor_key'] = $this->generateKey();
        }

        if ($id > 0) {
            MonitorTerminal::where('id', $id)->update($data);
            return MonitorTerminal::where('id', $id)->findOrFail()->toArray();
        }

        $data['created_at'] = $timestamp;
        $terminal = MonitorTerminal::create($data);
        return $terminal->toArray();
    }

    public function toggle(int $id): void
    {
        $terminal = MonitorTerminal::where('id', $id)->findOrFail();
        $next = (string) $terminal['status'] === 'enabled' ? 'disabled' : 'enabled';

        MonitorTerminal::where('id', $id)->update([
            'status' => $next,
            'updated_at' => time(),
        ]);
    }

    public function resetKey(int $id): string
    {
        $key = $this->generateKey();
        MonitorTerminal::where('id', $id)->update([
            'monitor_key' => $key,
            'updated_at' => time(),
        ]);

        return $key;
    }

    public function legacyDefaultMonitorKey(): string
    {
        try {
            $terminal = MonitorTerminal::where('terminal_code', 'legacy-default')->find();
            if ($terminal) {
                return (string) $terminal['monitor_key'];
            }
        } catch (\Throwable) {
            // Legacy tests may not have the terminal table available yet.
        }

        return $this->getConfigValue('monitorKey');
    }

    public function updateLegacyDefaultMonitorKey(string $monitorKey): void
    {
        try {
            $terminal = MonitorTerminal::where('terminal_code', 'legacy-default')->find();
            if ($terminal) {
                MonitorTerminal::where('id', $terminal['id'])->update([
                    'monitor_key' => $monitorKey,
                    'updated_at' => time(),
                ]);
            }
        } catch (\Throwable) {
            // Fall back to the legacy config storage when terminal tables are unavailable.
        }

        $this->configRepository()->set('monitorKey', $monitorKey);
    }

    protected function getConfigValue(string $key, string $default = ''): string
    {
        return $this->configRepository()->get($key, $default);
    }

    protected function configRepository(): SettingConfigRepository
    {
        return new SettingConfigRepository();
    }

    protected function generateKey(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return md5(uniqid((string) mt_rand(), true));
        }
    }

    private function normalizeDeviceMeta(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}
