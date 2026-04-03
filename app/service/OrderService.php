<?php
declare(strict_types=1);

namespace app\service;

use app\model\PayOrder;
use app\model\PayQrcode;
use app\model\Setting;
use app\model\TmpPrice;

class OrderService
{
    /**
     * 创建订单核心逻辑
     * 返回订单数据数组（用于 JSON 响应或 HTML 跳转）
     * @throws \RuntimeException on business logic errors
     */
    public static function createOrder(array $params): array
    {
        $payId = $params['payId'];
        $type = (int)$params['type'];
        $price = $params['price'];
        $param = $params['param'] ?? '';
        $notifyUrl = $params['notifyUrl'] ?? Setting::getConfigValue('notifyUrl');
        $returnUrl = $params['returnUrl'] ?? Setting::getConfigValue('returnUrl');

        // 检查监控端状态
        $jkstate = Setting::getConfigValue('jkstate');
        if ($jkstate !== '1') {
            throw new \RuntimeException('监控端状态异常，请检查');
        }

        // 竞价分配唯一金额
        $reallyPrice = bcmul((string)$price, '100');
        $payQf = Setting::getConfigValue('payQf');
        $orderId = date('YmdHms') . rand(1, 9) . rand(1, 9) . rand(1, 9) . rand(1, 9);

        $ok = false;
        for ($i = 0; $i < 10; $i++) {
            $tmpPrice = $reallyPrice . '-' . $type;
            try {
                TmpPrice::create(['price' => $tmpPrice, 'oid' => $orderId]);
                $ok = true;
                break;
            } catch (\Exception $e) {
                // 价格冲突，继续尝试
            }
            if ($payQf == 1) {
                $reallyPrice++;
            } elseif ($payQf == 2) {
                $reallyPrice--;
            }
        }

        if (!$ok) {
            throw new \RuntimeException('订单超出负荷，请稍后重试');
        }

        $reallyPrice = bcdiv((string)$reallyPrice, '100', 2);

        // 获取支付 URL
        $payUrl = static::getPayUrl($type);
        if ($payUrl === '') {
            throw new \RuntimeException('请您先进入后台配置程序');
        }

        $isAuto = 1;
        $matchedQrcode = PayQrcode::where('price', $reallyPrice)
            ->where('type', $type)
            ->find();
        if ($matchedQrcode) {
            $payUrl = $matchedQrcode['pay_url'];
            $isAuto = 0;
        }

        // 检查商户订单号唯一性
        $exists = PayOrder::where('pay_id', $payId)->find();
        if ($exists) {
            throw new \RuntimeException('商户订单号已存在');
        }

        $createDate = time();
        $data = [
            'close_date'   => 0,
            'create_date'  => $createDate,
            'is_auto'      => $isAuto,
            'notify_url'   => $notifyUrl,
            'order_id'     => $orderId,
            'param'        => $param,
            'pay_date'     => 0,
            'pay_id'       => $payId,
            'pay_url'      => $payUrl,
            'price'        => $price,
            'really_price' => $reallyPrice,
            'return_url'   => $returnUrl,
            'state'        => PayOrder::STATE_UNPAID,
            'type'         => $type,
        ];

        PayOrder::create($data);

        $time = Setting::getConfigValue('close');

        $orderInfo = [
            'payId'       => $payId,
            'orderId'     => $orderId,
            'payType'     => $type,
            'price'       => $price,
            'reallyPrice' => $reallyPrice,
            'payUrl'      => $payUrl,
            'isAuto'      => $isAuto,
            'state'       => PayOrder::STATE_UNPAID,
            'timeOut'     => $time,
            'date'        => $createDate,
        ];

        CacheService::cacheOrder($orderId, $orderInfo);

        return $orderInfo;
    }

    /**
     * 处理支付推送，匹配订单并发送通知
     * 返回: ['matched' => bool, 'alreadyProcessed' => bool, 'notifyOk' => bool]
     */
    public static function handlePayPush(string $price, int $type): array
    {
        Setting::setConfigValue('lastpay', (string)time());

        $res = PayOrder::where('really_price', $price)
            ->where('state', PayOrder::STATE_UNPAID)
            ->where('type', $type)
            ->find();

        if (!$res) {
            // 无订单转账，记录入库
            PayOrder::create([
                'close_date'   => 0,
                'create_date'  => time(),
                'is_auto'      => 0,
                'notify_url'   => '',
                'order_id'     => '无订单转账',
                'param'        => '无订单转账',
                'pay_date'     => 0,
                'pay_id'       => '无订单转账',
                'pay_url'      => '',
                'price'        => $price,
                'really_price' => $price,
                'return_url'   => '',
                'state'        => PayOrder::STATE_PAID,
                'type'         => $type,
            ]);

            return ['matched' => false, 'alreadyProcessed' => false, 'notifyOk' => true];
        }

        // 乐观锁更新，防止并发重复处理
        $affected = PayOrder::where('id', $res['id'])
            ->where('state', PayOrder::STATE_UNPAID)
            ->update(['state' => PayOrder::STATE_PAID, 'pay_date' => time(), 'close_date' => time()]);

        if ($affected === 0) {
            return ['matched' => true, 'alreadyProcessed' => true, 'notifyOk' => true];
        }

        TmpPrice::where('oid', $res['order_id'])->delete();

        $notifyOk = NotifyService::sendNotify($res->toArray());

        if (!$notifyOk) {
            PayOrder::where('id', $res['id'])->update(['state' => PayOrder::STATE_NOTIFY_FAILED]);
        }

        return ['matched' => true, 'alreadyProcessed' => false, 'notifyOk' => $notifyOk];
    }

    private static function getPayUrl(int $type): string
    {
        if ($type === PayOrder::TYPE_WECHAT) {
            return Setting::getConfigValue('wxpay');
        }
        if ($type === PayOrder::TYPE_ALIPAY) {
            return Setting::getConfigValue('zfbpay');
        }
        return '';
    }
}
