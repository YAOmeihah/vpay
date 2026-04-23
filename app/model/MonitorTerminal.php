<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 监控终端模型
 */
class MonitorTerminal extends Model
{
    protected $name = 'monitor_terminal';

    protected $pk = 'id';

    protected $schema = [
        'id' => 'bigint',
        'terminal_code' => 'string',
        'terminal_name' => 'string',
        'status' => 'string',
        'online_state' => 'string',
        'monitor_key' => 'string',
        'last_heartbeat_at' => 'bigint',
        'last_paid_at' => 'bigint',
        'last_ip' => 'string',
        'device_meta' => 'string',
        'created_at' => 'bigint',
        'updated_at' => 'bigint',
    ];
}
