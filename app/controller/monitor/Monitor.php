<?php
declare(strict_types=1);

namespace app\controller\monitor;

use app\BaseController;
use app\service\MonitorService;
use app\service\OrderService;
use app\service\SignService;
use app\service\runtime\SettingMonitorState;
use app\service\security\MonitorReplayGuard;

class Monitor extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function getState()
    {
        $t = $this->request->param("t");

        if (!$this->verifyMonitorSimpleSignature((string) $t, (string) $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $state = $this->monitorState();
        $lastheart = $state->getLastHeartbeatRaw();
        $lastpay = $state->getLastPaidRaw();
        $jkstate = $state->getOnlineFlagRaw();

        return json($this->getReturn(1, "成功", array("lastheart" => $lastheart, "lastpay" => $lastpay, "jkstate" => $jkstate)));
    }

    public function appHeart()
    {
        MonitorService::closeExpiredOrders();

        $t = $this->request->param("t");

        if (!$this->verifyMonitorSimpleSignature((string) $t, (string) $this->request->param('sign', ''))) {
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        MonitorService::heartbeat();
        return json($this->getReturn());
    }

    public function appPush()
    {
        $this->closeExpiredOrders();

        $type = (int) $this->request->param('type');
        $amountCents = (int) $this->request->param('amountCents');
        $ts = (int) $this->request->param('ts');
        $nonce = trim((string) $this->request->param('nonce', ''));
        $eventId = trim((string) $this->request->param('eventId', ''));
        $sign = (string) $this->request->param('sign', '');

        if ($amountCents <= 0 || $ts <= 0 || $nonce === '' || $eventId === '') {
            return json($this->getReturn(-1, "监控回调参数不完整"));
        }

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

        return json($this->getReturn(-1, "异步通知失败"));
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

    protected function validateMonitorReplay(string $eventId, string $nonce, int $timestamp): string
    {
        return $this->monitorReplayGuard()->assertValid($eventId, $nonce, $timestamp);
    }

    protected function handlePayPush(string $price, int $type): array
    {
        return OrderService::handlePayPush($price, $type);
    }

    protected function formatAmountCents(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }
}
