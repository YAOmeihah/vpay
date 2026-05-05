<?php
declare(strict_types=1);

namespace app\service\maintenance;

use app\service\MonitorService;
use app\service\config\SettingConfigRepository;
use app\service\notification\NotificationService;
use Throwable;

class MaintenanceService
{
    /**
     * @return array{
     *   run_at: int,
     *   tasks: array<int, array{name: string, status: string, affected: int}>
     * }
     */
    public function runScheduled(): array
    {
        $result = [
            'run_at' => time(),
            'tasks' => [
                $this->runTerminalOfflineCheck(),
                $this->runExpiredOrderCleanup(),
            ],
        ];

        $this->recordResult($result);

        return $result;
    }

    public function recordFailure(Throwable $exception): void
    {
        $this->recordResult([
            'run_at' => time(),
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
        $this->notificationService()->maintenanceException($exception);
    }

    /**
     * @return array{name: string, status: string, affected: int}
     */
    private function runTerminalOfflineCheck(): array
    {
        if (!$this->config()->taskEnabled('terminal_offline_check')) {
            return ['name' => 'terminal_offline_check', 'status' => 'skipped', 'affected' => 0];
        }

        $affected = MonitorService::checkMonitorTimeout();

        return ['name' => 'terminal_offline_check', 'status' => 'ok', 'affected' => $affected];
    }

    /**
     * @return array{name: string, status: string, affected: int}
     */
    private function runExpiredOrderCleanup(): array
    {
        if (!$this->config()->taskEnabled('expired_order_cleanup')) {
            return ['name' => 'expired_order_cleanup', 'status' => 'skipped', 'affected' => 0];
        }

        $affected = MonitorService::closeExpiredOrders(true, false);

        return ['name' => 'expired_order_cleanup', 'status' => 'ok', 'affected' => $affected];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function recordResult(array $result): void
    {
        $this->configRepository()->set('maintenance_last_run_at', (string) ($result['run_at'] ?? time()));
        $this->configRepository()->set(
            'maintenance_last_run_result',
            (string) json_encode($result, JSON_UNESCAPED_UNICODE)
        );
    }

    private function config(): MaintenanceConfig
    {
        return app()->make(MaintenanceConfig::class);
    }

    private function notificationService(): NotificationService
    {
        return app()->make(NotificationService::class);
    }

    private function configRepository(): SettingConfigRepository
    {
        return app()->make(SettingConfigRepository::class);
    }
}
