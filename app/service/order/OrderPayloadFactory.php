<?php
declare(strict_types=1);

namespace app\service\order;

class OrderPayloadFactory
{
    public function create(
        string $payId,
        string $orderId,
        int $payType,
        float|string $price,
        float|string $reallyPrice,
        string $payUrl,
        int $isAuto,
        int $state,
        int|string $timeOut,
        int $date
    ): array {
        return [
            'payId' => $payId,
            'orderId' => $orderId,
            'payType' => $payType,
            'price' => $price,
            'reallyPrice' => $reallyPrice,
            'payUrl' => $payUrl,
            'isAuto' => $isAuto,
            'state' => $state,
            'timeOut' => $timeOut,
            'date' => $date,
        ];
    }
}
