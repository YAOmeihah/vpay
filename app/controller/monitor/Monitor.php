<?php
declare(strict_types=1);

namespace app\controller\monitor;

use app\BaseController;
use app\model\MonitorTerminal;
use app\service\MonitorService;
use app\service\OrderService;
use app\service\SignService;
use app\service\payment\PaymentEventService;
use app\service\runtime\SettingMonitorState;
use app\service\security\MonitorReplayGuard;
use app\service\terminal\TerminalCredentialService;

class Monitor extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function getState()
    {
        $terminalCode = trim((string) $this->request->param('terminalCode', ''));
        $t = (string) $this->request->param("t", (string) $this->request->param('ts', ''));
        $sign = (string) $this->request->param('sign', '');

        if ($terminalCode === '') {
            if (!$this->verifyMonitorSimpleSignature((string) $t, $sign)) {
                return json($this->getReturn(-1, "签名校验不通过"));
            }

            $state = $this->monitorState();
            $lastheart = $state->getLastHeartbeatRaw();
            $lastpay = $state->getLastPaidRaw();
            $jkstate = $state->getOnlineFlagRaw();

            return json($this->getReturn(1, "成功", array("lastheart" => $lastheart, "lastpay" => $lastpay, "jkstate" => $jkstate)));
        }

        try {
            $terminal = $this->resolveTerminal($terminalCode);
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }

        if (!$this->verifyTerminalMonitorSimpleSignature((string) $terminal['terminal_code'], (string) $t, $sign)) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        return json($this->getReturn(1, "成功", array(
            "lastheart" => (string) $terminal['last_heartbeat_at'],
            "lastpay" => (string) $terminal['last_paid_at'],
            "jkstate" => (string) ((string) $terminal['online_state'] === 'online' ? '1' : '0'),
        )));
    }

    public function appHeart()
    {
        MonitorService::closeExpiredOrders();

        $terminalCode = trim((string) $this->request->param('terminalCode', ''));
        $t = (string) $this->request->param("t", (string) $this->request->param('ts', ''));
        $sign = (string) $this->request->param('sign', '');

        if ($terminalCode === '') {
            if (!$this->verifyMonitorSimpleSignature((string) $t, $sign)) {
                return json($this->getReturn(-1, "签名校验不通过"));
            }

            MonitorService::heartbeat();
            return json($this->getReturn());
        }

        try {
            $terminal = $this->resolveTerminal($terminalCode);
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }

        if (!$this->verifyTerminalMonitorSimpleSignature((string) $terminal['terminal_code'], (string) $t, $sign)) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $this->markTerminalHeartbeat((int) $terminal['id'], (string) $this->request->ip());
        return json($this->getReturn());
    }

    public function appPush()
    {
        $this->closeExpiredOrders();

        $terminalCode = trim((string) $this->request->param('terminalCode', ''));
        $type = (int) $this->request->param('type');
        $amountCents = (int) $this->request->param('amountCents');
        $ts = (int) $this->request->param('ts');
        $nonce = trim((string) $this->request->param('nonce', ''));
        $eventId = trim((string) $this->request->param('eventId', ''));
        $sign = (string) $this->request->param('sign', '');

        if ($amountCents <= 0 || $ts <= 0 || $nonce === '' || $eventId === '') {
            return json($this->getReturn(-1, "监控回调参数不完整"));
        }

        if ($terminalCode === '') {
            if (!$this->verifyMonitorPushSignature($type, $amountCents, $ts, $nonce, $eventId, $sign)) {
                return json($this->getReturn(-1, "签名校验不通过"));
            }

            try {
                $guardResult = $this->validateMonitorReplay($eventId, $nonce, $ts);
            } catch (\RuntimeException $e) {
                return json($this->getReturn(-1, $e->getMessage()));
            }

            if ($guardResult === 'duplicate') {
                return json($this->getReturn(1, "监控事件已处理"));
            }

            $result = $this->handlePayPush($this->formatAmountCents($amountCents), $type);

            if ($result['alreadyProcessed']) {
                return json($this->getReturn(1, "订单已处理"));
            }

            if ($result['notifyOk']) {
                return json($this->getReturn());
            }

            return json($this->getReturn(-1, "异步通知失败", $result['notifyDetail'] ?? ''));
        }

        try {
            $terminal = $this->resolveTerminal($terminalCode);
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }

        $effectiveTerminalCode = (string) $terminal['terminal_code'];

        if (!$this->verifyTerminalMonitorPushSignature($effectiveTerminalCode, $type, $amountCents, $ts, $nonce, $eventId, $sign)) {
            $this->recordInvalidSignature($terminal, $type, $amountCents, $eventId, (array) $this->request->param());
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        try {
            $guardResult = $this->validateMonitorReplay($eventId, $nonce, $ts);
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }

        if ($guardResult === 'duplicate') {
            return json($this->getReturn(1, "监控事件已处理"));
        }

        $result = $this->handleTerminalPayPush(
            (int) $terminal['id'],
            $this->formatAmountCents($amountCents),
            $type,
            $eventId,
            (array) $this->request->param()
        );

        if ($result['alreadyProcessed']) {
            return json($this->getReturn(1, "订单已处理"));
        }

        if ($result['notifyOk']) {
            return json($this->getReturn());
        }

        return json($this->getReturn(-1, "异步通知失败", $result['notifyDetail'] ?? ''));
    }

    public function closeEndOrder()
    {
        $affected = MonitorService::closeExpiredOrders(true);

        if ($affected > 0) {
            return json($this->getReturn(1, "成功清理" . $affected . "条订单"));
        }

        return json($this->getReturn(1, "没有等待清理的订单"));
    }

    private function monitorState(): SettingMonitorState
    {
        return $this->app->make(SettingMonitorState::class);
    }

    protected function monitorReplayGuard(): MonitorReplayGuard
    {
        return $this->app->make(MonitorReplayGuard::class);
    }

    protected function closeExpiredOrders(): void
    {
        MonitorService::closeExpiredOrders();
    }

    protected function verifyMonitorPushSignature(
        int $type,
        int $amountCents,
        int $ts,
        string $nonce,
        string $eventId,
        string $sign
    ): bool {
        return SignService::verifyMonitorPushSign($type, $amountCents, $ts, $nonce, $eventId, $sign);
    }

    protected function verifyMonitorSimpleSignature(string $data, string $sign): bool
    {
        return SignService::verifyMonitorSimpleSign($data, $sign);
    }

    protected function verifyTerminalMonitorPushSignature(
        string $terminalCode,
        int $type,
        int $amountCents,
        int $ts,
        string $nonce,
        string $eventId,
        string $sign
    ): bool {
        return SignService::verifyTerminalMonitorPushSign($terminalCode, $type, $amountCents, $ts, $nonce, $eventId, $sign);
    }

    protected function verifyTerminalMonitorSimpleSignature(string $terminalCode, string $data, string $sign): bool
    {
        return SignService::verifyTerminalMonitorSimpleSign($terminalCode, $data, $sign);
    }

    protected function validateMonitorReplay(string $eventId, string $nonce, int $timestamp): string
    {
        return $this->monitorReplayGuard()->assertValid($eventId, $nonce, $timestamp);
    }

    protected function handlePayPush(string $price, int $type): array
    {
        return OrderService::handlePayPush($price, $type);
    }

    protected function handleTerminalPayPush(
        int $terminalId,
        string $price,
        int $type,
        string $eventId,
        array $rawPayload
    ): array {
        return OrderService::handleTerminalPayPush($terminalId, $price, $type, $eventId, $rawPayload);
    }

    protected function resolveTerminal(string $terminalCode): MonitorTerminal
    {
        return $this->terminalCredentialService()->requireTerminal($terminalCode);
    }

    protected function markTerminalHeartbeat(int $terminalId, string $ip): void
    {
        MonitorService::heartbeatForTerminal($terminalId, $ip);
    }

    protected function recordInvalidSignature(
        MonitorTerminal $terminal,
        int $type,
        int $amountCents,
        string $eventId,
        array $rawPayload
    ): void {
        $this->paymentEventService()->recordInvalidSignature($terminal, $type, $amountCents, $eventId, $rawPayload);
    }

    protected function terminalCredentialService(): TerminalCredentialService
    {
        return $this->app->make(TerminalCredentialService::class);
    }

    protected function paymentEventService(): PaymentEventService
    {
        return $this->app->make(PaymentEventService::class);
    }

    protected function formatAmountCents(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }
}
