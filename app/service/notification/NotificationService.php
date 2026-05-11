<?php
declare(strict_types=1);

namespace app\service\notification;

use app\model\PayOrder;
use app\service\maintenance\MaintenanceConfig;
use Throwable;

class NotificationService
{
    /**
     * @param array<string, mixed> $terminal
     */
    public function terminalOffline(array $terminal): bool
    {
        if (!$this->config()->eventEnabled('terminal_offline')) {
            return false;
        }

        return $this->send('监控端离线: ' . $this->terminalLabel($terminal));
    }

    /**
     * @param array<string, mixed> $terminal
     */
    public function terminalRecovered(array $terminal): bool
    {
        if (!$this->config()->eventEnabled('terminal_recovered')) {
            return false;
        }

        return $this->send('监控端恢复在线: ' . $this->terminalLabel($terminal));
    }

    public function expiredOrdersClosed(int $count): bool
    {
        if ($count <= 0 || !$this->config()->eventEnabled('expired_order_cleanup')) {
            return false;
        }

        return $this->send('维护任务已关闭过期订单: ' . $count . ' 笔');
    }

    public function maintenanceException(Throwable $exception): bool
    {
        if (!$this->config()->eventEnabled('maintenance_exception')) {
            return false;
        }

        return $this->send('维护任务异常: ' . $exception->getMessage());
    }

    /**
     * @param array<string, mixed> $order
     */
    public function paymentSuccess(array $order, ?bool $merchantNotifyOk = null, string $merchantNotifyDetail = ''): bool
    {
        if (!$this->config()->eventEnabled('payment_success')) {
            return false;
        }

        $lines = [
            '支付成功',
            '系统订单: ' . $this->orderValue($order, 'order_id'),
            '商户订单: ' . $this->orderValue($order, 'pay_id'),
            '支付方式: ' . $this->paymentTypeLabel((int) ($order['type'] ?? 0)),
            '订单金额: ' . $this->orderAmount($order, 'price'),
            '实付金额: ' . $this->orderAmount($order, 'really_price'),
        ];

        if ($this->config()->paymentSuccessCallbackStatusEnabled() && $merchantNotifyOk !== null) {
            $lines[] = '商户回调: ' . ($merchantNotifyOk ? '成功' : '失败');
            $detail = trim($merchantNotifyDetail);
            if (!$merchantNotifyOk && $detail !== '') {
                $lines[] = '回调详情: ' . $detail;
            }
        }

        return $this->send(implode("\n", $lines));
    }

    public function sendTestMessage(): bool
    {
        return $this->send('维护通知测试: Telegram 配置可用');
    }

    protected function send(string $message): bool
    {
        if (!$this->telegramReady()) {
            return false;
        }

        return $this->telegramChannel()->send($message);
    }

    /**
     * @param array<string, mixed> $terminal
     */
    private function terminalLabel(array $terminal): string
    {
        $code = (string) ($terminal['terminal_code'] ?? '');
        $name = (string) ($terminal['terminal_name'] ?? '');

        return trim($name . ($code !== '' ? ' [' . $code . ']' : ''));
    }

    /**
     * @param array<string, mixed> $order
     */
    private function orderValue(array $order, string $key): string
    {
        $value = trim((string) ($order[$key] ?? ''));

        return $value !== '' ? $value : '-';
    }

    /**
     * @param array<string, mixed> $order
     */
    private function orderAmount(array $order, string $key): string
    {
        $value = trim((string) ($order[$key] ?? ''));
        if ($value === '') {
            return '-';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function paymentTypeLabel(int $type): string
    {
        return match ($type) {
            PayOrder::TYPE_WECHAT => '微信',
            PayOrder::TYPE_ALIPAY => '支付宝',
            default => '未知',
        };
    }

    private function telegramReady(): bool
    {
        $config = $this->config();

        return $config->telegramEnabled()
            && $config->telegramBotToken() !== ''
            && $config->telegramChatId() !== '';
    }

    protected function telegramChannel(): TelegramNotificationChannel
    {
        return app()->make(TelegramNotificationChannel::class);
    }

    private function config(): MaintenanceConfig
    {
        return app()->make(MaintenanceConfig::class);
    }
}
