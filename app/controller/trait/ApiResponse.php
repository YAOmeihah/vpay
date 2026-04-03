<?php
declare(strict_types=1);

namespace app\controller\trait;

trait ApiResponse
{
    protected function getReturn(int $code = 1, string $msg = "成功", mixed $data = null): array
    {
        return ['code' => $code, 'msg' => $msg, 'data' => $data];
    }

    protected function success(mixed $data = null, string $msg = "成功"): \think\response\Json
    {
        return json($this->getReturn(1, $msg, $data));
    }

    protected function error(string $msg = "失败", int $code = -1): \think\response\Json
    {
        return json($this->getReturn($code, $msg));
    }
}
