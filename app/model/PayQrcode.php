<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 支付二维码模型
 */
class PayQrcode extends Model
{
    // 设置表名
    protected $name = 'pay_qrcode';
    
    // 设置主键
    protected $pk = 'id';
    
    // 设置字段信息
    protected $schema = [
        'id'      => 'bigint',
        'pay_url' => 'string',
        'price'   => 'float',
        'type'    => 'int',
    ];
    
    // 支付类型常量
    const TYPE_WECHAT = 1;     // 微信支付
    const TYPE_ALIPAY = 2;     // 支付宝支付
    
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
