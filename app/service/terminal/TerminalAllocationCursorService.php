<?php
declare(strict_types=1);

namespace app\service\terminal;

use think\facade\Db;

/**
 * Manages the per-payment-type cursor used by round-robin allocation.
 */
class TerminalAllocationCursorService
{
    public function lastChannelIdForUpdate(int $type): ?int
    {
        $row = $this->lockedRow($type);
        if ($row === null) {
            $this->ensureRow($type);
            $row = $this->lockedRow($type);
        }

        if ($row === null || (int) ($row['last_channel_id'] ?? 0) <= 0) {
            return null;
        }

        return (int) $row['last_channel_id'];
    }

    public function markChannelUsed(int $type, int $channelId): void
    {
        $this->ensureRow($type);

        Db::name('terminal_allocation_cursor')
            ->where('type', $type)
            ->update([
                'last_channel_id' => $channelId,
                'updated_at' => time(),
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lockedRow(int $type): ?array
    {
        $row = Db::name('terminal_allocation_cursor')
            ->where('type', $type)
            ->lock(true)
            ->find();

        return is_array($row) ? $row : null;
    }

    private function ensureRow(int $type): void
    {
        try {
            Db::name('terminal_allocation_cursor')->insert([
                'type' => $type,
                'last_channel_id' => 0,
                'updated_at' => time(),
            ]);
        } catch (\Throwable) {
            // Concurrent creators may race on the primary key; the later locked read handles it.
        }
    }
}
