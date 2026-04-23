<?php
declare(strict_types=1);

namespace app\service\payment;

use app\model\MonitorTerminal;
use app\model\PaymentEvent;

/**
 * 支付事件记录服务
 */
class PaymentEventService
{
    /**
     * @param array<string, mixed> $rawPayload
     */
    public function recordInvalidSignature(MonitorTerminal $terminal, int $type, int $amountCents, string $eventId, array $rawPayload): void
    {
        $this->upsert(
            (int) $terminal['id'],
            null,
            $eventId,
            $type,
            $amountCents,
            '',
            'invalid_signature',
            $rawPayload
        );
    }

    /**
     * @param array<string, mixed> $rawPayload
     */
    public function recordUnmatched(int $terminalId, string $eventId, int $type, int $amountCents, array $rawPayload): void
    {
        $this->upsert($terminalId, null, $eventId, $type, $amountCents, '', 'unmatched', $rawPayload);
    }

    /**
     * @param array<string, mixed> $rawPayload
     */
    public function recordMatched(
        int $terminalId,
        int $channelId,
        string $eventId,
        int $type,
        int $amountCents,
        string $matchedOrderId,
        array $rawPayload
    ): void {
        $this->upsert($terminalId, $channelId, $eventId, $type, $amountCents, $matchedOrderId, 'matched', $rawPayload);
    }

    /**
     * @param array<string, mixed> $rawPayload
     */
    private function upsert(
        int $terminalId,
        ?int $channelId,
        string $eventId,
        int $type,
        int $amountCents,
        string $matchedOrderId,
        string $result,
        array $rawPayload
    ): void {
        $payload = [
            'channel_id' => $channelId,
            'type' => $type,
            'amount_cents' => $amountCents,
            'raw_payload' => json_encode($rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'matched_order_id' => $matchedOrderId,
            'result' => $result,
            'created_at' => time(),
        ];

        $existing = PaymentEvent::where('terminal_id', $terminalId)
            ->where('event_id', $eventId)
            ->find();

        if ($existing) {
            PaymentEvent::where('id', $existing['id'])->update($payload);
            return;
        }

        PaymentEvent::create($payload + [
            'terminal_id' => $terminalId,
            'event_id' => $eventId,
        ]);
    }
}
