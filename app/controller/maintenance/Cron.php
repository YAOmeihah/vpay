<?php
declare(strict_types=1);

namespace app\controller\maintenance;

use app\BaseController;
use app\service\maintenance\MaintenanceAccessDeniedException;
use app\service\maintenance\MaintenanceAccessGuard;
use app\service\maintenance\MaintenanceService;
use Throwable;

class Cron extends BaseController
{
    public function run()
    {
        try {
            $this->accessGuard()->assertAllowed($this->request);
        } catch (MaintenanceAccessDeniedException $exception) {
            return json([
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage(),
                'data' => null,
            ], 403);
        }

        try {
            return json([
                'code' => 1,
                'msg' => '维护完成',
                'data' => $this->maintenanceService()->runScheduled(),
            ]);
        } catch (Throwable $exception) {
            $this->maintenanceService()->recordFailure($exception);

            return json([
                'code' => 0,
                'msg' => '维护执行失败: ' . $exception->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    private function accessGuard(): MaintenanceAccessGuard
    {
        return $this->app->make(MaintenanceAccessGuard::class);
    }

    private function maintenanceService(): MaintenanceService
    {
        return $this->app->make(MaintenanceService::class);
    }
}
