<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 终端支付通道模型
 */
class TerminalChannel extends Model
{
    protected $name = 'terminal_channel';

    protected $pk = 'id';

    protected $schema = [
        'id' => 'bigint',
        'terminal_id' => 'bigint',
        'type' => 'int',
        'channel_name' => 'string',
        'status' => 'string',
        'pay_url' => 'string',
        'priority' => 'int',
        'last_used_at' => 'bigint',
        'created_at' => 'bigint',
        'updated_at' => 'bigint',
    ];
}
