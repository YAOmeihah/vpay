<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\AdminPermissionService;
use think\facade\Session;

class Profile extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function profile()
    {
        $username = Session::get('admin_user');

        if (!$username) {
            return $this->respond(40101, '没有登录', null, 401);
        }

        return $this->success([
            'avatar' => '',
            'username' => (string) $username,
            'nickname' => '管理员',
            'roles' => ['admin'],
            'permissions' => $this->adminPermissionService()->all(),
        ]);
    }

    public function logout()
    {
        Session::clear();
        Session::destroy();

        return $this->success(null, '退出成功');
    }

    private function adminPermissionService(): AdminPermissionService
    {
        return $this->app->make(AdminPermissionService::class);
    }
}
