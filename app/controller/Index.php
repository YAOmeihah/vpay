<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;

class Index extends BaseController
{
    public function index()
    {
        return 'ThinkPHP 8 支付系统已成功运行！';
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
}
