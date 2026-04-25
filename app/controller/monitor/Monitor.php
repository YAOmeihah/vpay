<?php
declare(strict_types=1);

namespace app\controller\monitor;

use app\BaseController;
use app\model\MonitorTerminal;
use app\service\MonitorService;
use app\service\OrderService;
use app\service\SignService;
use app\service\payment\PaymentEventService;
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
            return json($this->getReturn(-1, "终端编码不能为空"));
        }

        try {
            $terminal = $this->resolveTerminal($terminalCode);
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }

        if (!$this->verifyTerminalMonitorSimpleSignature((string) $terminal['terminal_code'], (string) $t, $sign)) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        try {
            $this->validateSimpleMonitorTimestamp((int) $t);
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }

        return json($this->getReturn(1, "成功", array(
            "lastheart" => (string) $terminal['last_heartbeat_at'],
            "lastpay" => (string) $terminal['last_paid_at'],
            "jkstate" => (string) ((string) $terminal['online_state'] === 'online' ? '1' : '0'),
        )));
    }

    public function appHeart()
    {
        $this->closeExpiredOrders();

        $terminalCode = trim((string) $this->request->param('terminalCode', ''));
        $t = (string) $this->request->param("t", (string) $this->request->param('ts', ''));
        $sign = (string) $this->request->param('sign', '');

        if ($terminalCode === '') {
            return json($this->getReturn(-1, "终端编码不能为空"));
        }

        try {
            $terminal = $this->resolveTerminal($terminalCode);
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
        }

        if (!$this->verifyTerminalMonitorSimpleSignature((string) $terminal['terminal_code'], (string) $t, $sign)) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        try {
            $this->validateHeartbeatReplay((string) $terminal['terminal_code'], (int) $t);
        } catch (\RuntimeException $e) {
            return json($this->getReturn(-1, $e->getMessage()));
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
            return json($this->getReturn(-1, "终端编码不能为空"));
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
            $guardResult = $this->validateMonitorReplay($eventId, $nonce, $ts, $effectiveTerminalCode);
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

    protected function monitorReplayGuard(): MonitorReplayGuard
    {
        return $this->app->make(MonitorReplayGuard::class);
    }

    protected function closeExpiredOrders(): void
    {
        MonitorService::closeExpiredOrders();
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

    protected function validateMonitorReplay(string $eventId, string $nonce, int $timestamp, ?string $scope = null): string
    {
        return $this->monitorReplayGuard()->assertValid($eventId, $nonce, $timestamp, (string) ($scope ?? ''));
    }

    protected function validateSimpleMonitorTimestamp(int $timestamp): void
    {
        $this->monitorReplayGuard()->assertFreshTimestamp($timestamp);
    }

    protected function validateHeartbeatReplay(string $terminalCode, int $timestamp): void
    {
        $result = $this->monitorReplayGuard()->assertValid(
            'app-heart:' . $timestamp,
            'app-heart:' . $timestamp,
            $timestamp,
            'app-heart:' . trim($terminalCode)
        );

        if ($result === 'duplicate') {
            throw new \RuntimeException('监控心跳已重放');
        }
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
