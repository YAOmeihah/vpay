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
            // 测试数据库连接
            $db = \think\facade\Db::connect();
            $result = $db->query('SELECT 1 as test');
            return json(['code' => 1, 'msg' => '数据库连接成功', 'data' => $result]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '数据库连接失败: ' . $e->getMessage()]);
        }
    }
}
