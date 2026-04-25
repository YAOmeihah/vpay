<?php
declare(strict_types=1);

use app\service\terminal\TerminalAllocatorService;
use PHPUnit\Framework\TestCase;

final class TerminalAllocatorServiceTest extends TestCase
{
    public function test_fixed_priority_picks_the_lowest_dispatch_priority_terminal_for_the_requested_type(): void
    {
        $allocator = new TerminalAllocatorService();

        $channel = $allocator->pickChannel('fixed_priority', [
            ['id' => 3, 'type' => 1, 'status' => 'enabled', 'terminal_status' => 'enabled', 'online_state' => 'online', 'dispatch_priority' => 50],
            ['id' => 8, 'type' => 1, 'status' => 'enabled', 'terminal_status' => 'enabled', 'online_state' => 'online', 'dispatch_priority' => 10],
        ], 1);

        self::assertSame(8, $channel['id']);
    }

    public function test_round_robin_skips_disabled_or_offline_terminals_even_when_payment_configs_are_enabled(): void
    {
        $allocator = new TerminalAllocatorService();

        $channel = $allocator->pickChannel('round_robin', [
            ['id' => 3, 'type' => 1, 'status' => 'enabled', 'terminal_status' => 'disabled', 'online_state' => 'online', 'dispatch_priority' => 10],
            ['id' => 4, 'type' => 1, 'status' => 'enabled', 'terminal_status' => 'enabled', 'online_state' => 'online', 'dispatch_priority' => 20],
        ], 1, 3);

        self::assertSame(4, $channel['id']);
    }

    public function test_round_robin_single_eligible_channel_keeps_returning_that_channel(): void
    {
        $allocator = new TerminalAllocatorService();

        $channel = $allocator->pickChannel('round_robin', [
            ['id' => 9, 'terminal_id' => 90, 'type' => 1, 'status' => 'enabled', 'terminal_status' => 'enabled', 'online_state' => 'online', 'dispatch_priority' => 10],
        ], 1, 9);

        self::assertSame(9, $channel['id']);
    }

    public function test_round_robin_rotates_from_last_channel_to_next_eligible_channel(): void
    {
        $allocator = new TerminalAllocatorService();

        $ordered = $allocator->orderEligibleChannels('round_robin', [
            ['id' => 3, 'terminal_id' => 30, 'type' => 1, 'status' => 'enabled', 'terminal_status' => 'enabled', 'online_state' => 'online', 'dispatch_priority' => 10],
            ['id' => 4, 'terminal_id' => 40, 'type' => 1, 'status' => 'enabled', 'terminal_status' => 'enabled', 'online_state' => 'online', 'dispatch_priority' => 20],
            ['id' => 5, 'terminal_id' => 50, 'type' => 1, 'status' => 'enabled', 'terminal_status' => 'enabled', 'online_state' => 'online', 'dispatch_priority' => 30],
        ], 1, 4);

        self::assertSame([5, 3, 4], array_column($ordered, 'id'));
    }
}
