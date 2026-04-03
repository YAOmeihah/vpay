<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class OrderValidate extends Validate
{
    protected $rule = [
        'payId'     => 'require|max:100',
        'type'      => 'require|in:1,2',
        'price'     => 'require|float|gt:0|lt:1000000',
        'sign'      => 'require',
        'notifyUrl' => 'max:1000',
        'returnUrl' => 'max:1000',
    ];

    protected $message = [
        'payId.require'  => '请传入商户订单号',
        'payId.max'      => '商户订单号长度超限',
        'type.require'   => '请传入支付方式',
        'type.in'        => '支付方式错误=>1|微信 2|支付宝',
        'price.require'  => '请传入订单金额',
        'price.float'    => '订单金额格式错误',
        'price.gt'       => '订单金额必须大于0',
        'price.lt'       => '订单金额超出限制',
        'sign.require'   => '请传入签名',
        'notifyUrl.max'  => '回调地址长度超限',
        'returnUrl.max'  => '返回地址长度超限',
    ];
}
