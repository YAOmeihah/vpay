<?php
declare(strict_types=1);

namespace tests;

use app\model\TerminalChannel;
use app\service\admin\ChannelAdminService;

class ChannelAdminServiceTest extends TestCase
{
    public function test_list_for_terminal_returns_fixed_payment_slots_even_when_one_side_is_missing(): void
    {
        TerminalChannel::where('terminal_id', 1)->where('type', 2)->delete();

        $service = new ChannelAdminService();

        $slots = $service->listForTerminal(1);

        $this->assertCount(2, $slots);
        $this->assertSame([1, 2], array_column($slots, 'type'));

        $wechat = $slots[0];
        $alipay = $slots[1];

        $this->assertTrue($wechat['exists']);
        $this->assertSame('微信', $wechat['slot_label']);
        $this->assertSame(1, $wechat['id']);
        $this->assertSame('默认微信通道', $wechat['channel_name']);
        $this->assertArrayNotHasKey('priority', $wechat);

        $this->assertFalse($alipay['exists']);
        $this->assertSame('支付宝', $alipay['slot_label']);
        $this->assertNull($alipay['id']);
        $this->assertSame(1, $alipay['terminal_id']);
        $this->assertSame('支付宝收款', $alipay['channel_name']);
        $this->assertSame('', $alipay['pay_url']);
        $this->assertArrayNotHasKey('priority', $alipay);
    }

    public function test_save_upserts_existing_slot_by_terminal_and_payment_type(): void
    {
        $service = new ChannelAdminService();

        $updated = $service->save([
            'terminalId' => 1,
            'type' => 1,
            'channelName' => '微信收款更新',
            'payUrl' => 'wxp://updated-default',
            'status' => 'enabled',
        ]);

        $this->assertSame(1, (int) $updated['id']);
        $this->assertArrayNotHasKey('priority', $updated);

        $rows = TerminalChannel::where('terminal_id', 1)->where('type', 1)->select()->toArray();

        $this->assertCount(1, $rows);
        $this->assertSame('微信收款更新', $rows[0]['channel_name']);
        $this->assertSame('wxp://updated-default', $rows[0]['pay_url']);
        $this->assertArrayNotHasKey('priority', $rows[0]);
    }
}
