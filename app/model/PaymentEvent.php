<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 支付事件模型
 */
class PaymentEvent extends Model
{
    protected $name = 'payment_event';

    protected $pk = 'id';

    protected $schema = [
        'id' => 'bigint',
        'terminal_id' => 'bigint',
        'channel_id' => 'bigint',
        'event_id' => 'string',
        'type' => 'int',
        'amount_cents' => 'int',
        'raw_payload' => 'string',
        'matched_order_id' => 'string',
        'result' => 'string',
        'created_at' => 'bigint',
    ];
}
