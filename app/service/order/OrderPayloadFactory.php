<?php
declare(strict_types=1);

namespace app\service\order;

use app\model\PayOrder;

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

    /**
     * @param array<int, array{type: int, name: string}> $availablePayTypes
     */
    public function createPendingChoice(
        string $payId,
        string $orderId,
        int $payType,
        float|string $price,
        int $state,
        int|string $timeOut,
        int $date,
        string $assignReason,
        array $availablePayTypes
    ): array {
        $payload = $this->create(
            $payId,
            $orderId,
            $payType,
            $price,
            '',
            '',
            0,
            $state,
            $timeOut,
            $date
        );

        $payload['assignStatus'] = PayOrder::ASSIGN_STATUS_PENDING_CHOICE;
        $payload['assignReason'] = $assignReason;
        $payload['availablePayTypes'] = $availablePayTypes;

        return $payload;
    }
}
