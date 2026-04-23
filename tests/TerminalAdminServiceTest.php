<?php
declare(strict_types=1);

namespace tests;

use app\model\MonitorTerminal;
use app\model\PayOrder;
use app\model\TerminalChannel;
use app\service\admin\TerminalAdminService;
use think\facade\Db;

class TerminalAdminServiceTest extends TestCase
{
    public function test_save_persists_dispatch_priority_on_the_terminal_itself(): void
    {
        $service = new TerminalAdminService();

        $saved = $service->save([
            'terminalCode' => 'term-a',
            'terminalName' => '终端A',
            'status' => 'enabled',
            'monitorKey' => 'term-a-key',
            'dispatchPriority' => 5,
        ]);

        $this->assertSame(5, (int) ($saved['dispatch_priority'] ?? 0));
    }

    public function test_paginate_orders_terminals_by_dispatch_priority_then_id(): void
    {
        $service = new TerminalAdminService();

        $service->save([
            'terminalCode' => 'term-high',
            'terminalName' => '高顺序终端',
            'status' => 'enabled',
            'monitorKey' => 'term-high-key',
            'dispatchPriority' => 30,
        ]);

        $service->save([
            'terminalCode' => 'term-low',
            'terminalName' => '低顺序终端',
            'status' => 'enabled',
            'monitorKey' => 'term-low-key',
            'dispatchPriority' => 5,
        ]);

        $rows = $service->paginate(['page' => 1, 'limit' => 10])['data'];

        $this->assertSame(
            ['term-low', 'default-terminal', 'term-high'],
            array_column($rows, 'terminal_code')
        );
    }

    public function test_find_returns_a_single_terminal_for_detail_views(): void
    {
        $service = new TerminalAdminService();

        $saved = $service->save([
            'terminalCode' => 'term-detail',
            'terminalName' => '终端详情',
            'status' => 'enabled',
            'monitorKey' => 'term-detail-key',
            'dispatchPriority' => 20,
        ]);

        $this->assertTrue(method_exists($service, 'find'));
        $found = $service->find((int) $saved['id']);

        $this->assertSame('term-detail', $found['terminal_code']);
        $this->assertSame('终端详情', $found['terminal_name']);
    }

    public function test_delete_removes_terminal_and_owned_channels_when_no_open_orders(): void
    {
        $service = new TerminalAdminService();

        $saved = $service->save([
            'terminalCode' => 'term-delete',
            'terminalName' => '待删除终端',
            'status' => 'enabled',
            'monitorKey' => 'term-delete-key',
            'dispatchPriority' => 40,
        ]);
        $terminalId = (int) $saved['id'];

        TerminalChannel::create([
            'terminal_id' => $terminalId,
            'type' => 1,
            'channel_name' => '删除测试微信',
            'status' => 'enabled',
            'pay_url' => 'wxp://delete-test',
            'last_used_at' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $service->delete($terminalId);

        $this->assertNull(MonitorTerminal::where('id', $terminalId)->find());
        $this->assertSame(0, TerminalChannel::where('terminal_id', $terminalId)->count());
    }

    public function test_delete_refuses_terminal_with_unpaid_orders(): void
    {
        $service = new TerminalAdminService();

        $saved = $service->save([
            'terminalCode' => 'term-open-order',
            'terminalName' => '有未支付订单终端',
            'status' => 'enabled',
            'monitorKey' => 'term-open-order-key',
            'dispatchPriority' => 41,
        ]);
        $terminalId = (int) $saved['id'];

        Db::name('pay_order')->insert([
            'order_id' => 'open-order-delete-block',
            'pay_id' => 'pay-open-order-delete-block',
            'type' => PayOrder::TYPE_WECHAT,
            'price' => 1.00,
            'really_price' => 1.00,
            'pay_url' => 'wxp://open-order',
            'terminal_id' => $terminalId,
            'channel_id' => 0,
            'terminal_snapshot' => '有未支付订单终端',
            'channel_snapshot' => '微信',
            'state' => PayOrder::STATE_UNPAID,
            'create_date' => time(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('该终端存在未支付订单，不能删除');

        try {
            $service->delete($terminalId);
        } finally {
            $this->assertNotNull(MonitorTerminal::where('id', $terminalId)->find());
        }
    }
}
