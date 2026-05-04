<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;

class Admin extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function index()
    {
        return 'Admin Controller - ThinkPHP 8';
    }
}
