<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 支付类型维度的终端轮询游标。
 */
class TerminalAllocationCursor extends Model
{
    protected $name = 'terminal_allocation_cursor';

    protected $pk = 'type';

    public $increment = false;

    protected $schema = [
        'type' => 'int',
        'last_channel_id' => 'bigint',
        'updated_at' => 'bigint',
    ];
}
