<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\install\InstallStateService;
use think\Response;

class Index extends BaseController
{
    public function index()
    {
        $state = (string) ($this->installState()['state'] ?? 'installed');
        if (in_array($state, ['not_installed', 'upgrade_required'], true)) {
            return $this->redirectTo('/install');
        }

        if (in_array($state, ['locked', 'recovery_required'], true)) {
            return $this->redirectTo('/install/recover');
        }

        $portal = $this->app->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'index.html';
        if (is_file($portal)) {
            return response((string) file_get_contents($portal))->contentType('text/html');
        }

        return response('ThinkPHP 8 支付系统已成功运行！')->contentType('text/plain');
    }

    public function test()
    {
        try {
            $db = \think\facade\Db::connect();
            $result = $db->query('SELECT 1 as test');
            return json(['code' => 1, 'msg' => '数据库连接成功', 'data' => $result]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '数据库连接失败: ' . $e->getMessage()]);
        }
    }

    public function login()
    {
        // Compatibility entrypoint: delegate to split controller.
        return $this->delegateTo(\app\controller\admin\Auth::class, 'login');
    }

    public function createOrder()
    {
        // Compatibility entrypoint: delegate to split controller.
        return $this->delegateTo(\app\controller\merchant\Order::class, 'createOrder');
    }

    public function getOrder()
    {
        // Compatibility entrypoint: delegate to split controller.
        return $this->delegateTo(\app\controller\merchant\Order::class, 'getOrder');
    }

    public function checkOrder()
    {
        // Compatibility entrypoint: delegate to split controller.
        return $this->delegateTo(\app\controller\merchant\Order::class, 'checkOrder');
    }

    public function closeOrder()
    {
        // Compatibility entrypoint: delegate to split controller.
        return $this->delegateTo(\app\controller\merchant\Order::class, 'closeOrder');
    }

    /**
     * Delegate legacy Index endpoints to the split controllers.
     *
     * @return mixed The delegated controller action result (Json/Response/void).
     */
    private function delegateTo(string $controllerClass, string $method): mixed
    {
        $controller = $this->app->make($controllerClass);

        // Most controller actions use $this->request (from App) and take no args.
        return $controller->{$method}();
    }

    protected function installState(): array
    {
        return $this->app->make(InstallStateService::class)->status();
    }

    private function redirectTo(string $target): Response
    {
        return response('', 302, ['Location' => $target]);
    }
}
