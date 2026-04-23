<?php
declare(strict_types=1);

namespace app\service\admin;

use app\model\MonitorTerminal;
use app\model\PayOrder;
use app\model\TerminalChannel;
use think\facade\Db;

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

        $query = MonitorTerminal::order('dispatch_priority', 'asc')
            ->order('id', 'asc');
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
        $existing = $id > 0 ? MonitorTerminal::where('id', $id)->find() : null;
        $data = [
            'terminal_code' => trim((string) ($input['terminalCode'] ?? $input['terminal_code'] ?? '')),
            'terminal_name' => trim((string) ($input['terminalName'] ?? $input['terminal_name'] ?? '')),
            'dispatch_priority' => $this->normalizeDispatchPriority(
                $input['dispatchPriority']
                    ?? $input['dispatch_priority']
                    ?? ($existing['dispatch_priority'] ?? 100)
            ),
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

    /**
     * @return array<string, mixed>
     */
    public function find(int $id): array
    {
        return MonitorTerminal::where('id', $id)->findOrFail()->toArray();
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

    public function delete(int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('终端不存在');
        }

        $terminal = MonitorTerminal::where('id', $id)->find();
        if (!$terminal) {
            throw new \RuntimeException('终端不存在');
        }

        $openOrderId = Db::name('pay_order')
            ->where('terminal_id', $id)
            ->where('state', PayOrder::STATE_UNPAID)
            ->value('id');
        if ($openOrderId !== null) {
            throw new \RuntimeException('该终端存在未支付订单，不能删除');
        }

        Db::transaction(function () use ($id): void {
            TerminalChannel::where('terminal_id', $id)->delete();
            MonitorTerminal::where('id', $id)->delete();
        });
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

    private function normalizeDispatchPriority(mixed $value): int
    {
        return max(1, (int) $value);
    }
}
