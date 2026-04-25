<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 支付订单模型
 */
class PayOrder extends Model
{
    // 设置表名
    protected $name = 'pay_order';
    
    // 设置主键
    protected $pk = 'id';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'bigint',
        'close_date'  => 'bigint',
        'create_date' => 'bigint',
        'is_auto'     => 'int',
        'notify_url'  => 'string',
        'order_id'    => 'string',
        'param'       => 'string',
        'pay_date'    => 'bigint',
        'pay_id'      => 'string',
        'pay_url'     => 'string',
        'price'       => 'float',
        'really_price'=> 'float',
        'return_url'  => 'string',
        'terminal_id' => 'bigint',
        'channel_id'  => 'bigint',
        'assign_status' => 'string',
        'assign_reason' => 'string',
        'terminal_snapshot' => 'string',
        'channel_snapshot' => 'string',
        'state'       => 'int',
        'type'        => 'int',
    ];
    
    // 订单状态常量
    const STATE_UNPAID = 0;    // 未支付
    const STATE_PAID = 1;      // 已支付
    const STATE_NOTIFY_FAILED = 2; // 通知失败
    const STATE_EXPIRED = -1;  // 已过期
    const STATE_CANCELLED = -2; // 已取消
    const STATE_ASSIGN_FAILED = -3; // 分配失败

    // 分配状态常量
    const ASSIGN_STATUS_ASSIGNED = 'assigned';
    const ASSIGN_STATUS_PENDING_CHOICE = 'pending_choice';
    
    // 支付类型常量
    const TYPE_WECHAT = 1;     // 微信支付
    const TYPE_ALIPAY = 2;     // 支付宝支付
    
    /**
     * 获取订单状态文本
     */
    public function getStateTextAttr($value, $data)
    {
        $status = [
            self::STATE_UNPAID => '未支付',
            self::STATE_PAID => '已支付',
            self::STATE_NOTIFY_FAILED => '通知失败',
            self::STATE_EXPIRED => '已过期',
            self::STATE_CANCELLED => '已取消',
            self::STATE_ASSIGN_FAILED => '分配失败',
        ];
        return $status[$data['state']] ?? '未知状态';
    }
    
    /**
     * 获取支付类型文本
     */
    public function getTypeTextAttr($value, $data)
    {
        $types = [
            self::TYPE_WECHAT => '微信支付',
            self::TYPE_ALIPAY => '支付宝支付',
        ];
        return $types[$data['type']] ?? '未知类型';
    }
}
