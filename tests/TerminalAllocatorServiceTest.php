<?php
declare(strict_types=1);

use app\service\terminal\TerminalAllocatorService;
use PHPUnit\Framework\TestCase;

final class TerminalAllocatorServiceTest extends TestCase
{
    public function test_fixed_priority_picks_the_lowest_priority_enabled_online_channel(): void
    {
        $allocator = new TerminalAllocatorService();

        $channel = $allocator->pickChannel('fixed_priority', [
            ['id' => 8, 'type' => 1, 'priority' => 50, 'status' => 'enabled', 'online_state' => 'online'],
            ['id' => 3, 'type' => 1, 'priority' => 10, 'status' => 'enabled', 'online_state' => 'online'],
        ], 1);

        self::assertSame(3, $channel['id']);
    }

    public function test_round_robin_skips_disabled_or_offline_channels(): void
    {
        $allocator = new TerminalAllocatorService();

        $channel = $allocator->pickChannel('round_robin', [
            ['id' => 3, 'type' => 1, 'priority' => 10, 'status' => 'enabled', 'online_state' => 'offline'],
            ['id' => 4, 'type' => 1, 'priority' => 20, 'status' => 'enabled', 'online_state' => 'online'],
        ], 1, 3);

        self::assertSame(4, $channel['id']);
    }
}
