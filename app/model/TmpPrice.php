<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 临时价格模型
 */
class TmpPrice extends Model
{
    // 设置表名
    protected $name = 'tmp_price';
    
    // 设置主键
    protected $pk = 'oid';
    
    // 设置字段信息
    protected $schema = [
        'price' => 'string',
        'channel_id' => 'bigint',
        'oid'   => 'string',
    ];
}
