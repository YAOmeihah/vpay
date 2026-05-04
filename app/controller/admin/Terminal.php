<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\ChannelAdminService;
use app\service\admin\TerminalAdminService;

class Terminal extends BaseController
{
    use \app\controller\trait\ApiResponse;

    public function getTerminals()
    {
        return $this->success($this->terminalAdminService()->paginate($this->request->param()));
    }

    public function getTerminal()
    {
        return $this->success($this->terminalAdminService()->find((int) $this->request->param('id')));
    }

    public function saveTerminal()
    {
        return $this->success($this->terminalAdminService()->save($this->request->param()));
    }

    public function toggleTerminal()
    {
        $this->terminalAdminService()->toggle((int) $this->request->param('id'));
        return $this->success();
    }

    public function deleteTerminal()
    {
        try {
            $this->terminalAdminService()->delete((int) $this->request->param('id'));
            return $this->success();
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function resetTerminalKey()
    {
        $key = $this->terminalAdminService()->resetKey((int) $this->request->param('id'));
        return $this->success(['monitorKey' => $key]);
    }

    public function getTerminalChannels()
    {
        $terminalId = (int) $this->request->param('terminalId', $this->request->param('terminal_id', 0));
        return $this->success($this->channelAdminService()->listForTerminal($terminalId));
    }

    public function saveTerminalChannel()
    {
        return $this->success($this->channelAdminService()->save($this->request->param()));
    }

    public function toggleTerminalChannel()
    {
        $this->channelAdminService()->toggle((int) $this->request->param('id'));
        return $this->success();
    }

    private function terminalAdminService(): TerminalAdminService
    {
        return $this->app->make(TerminalAdminService::class);
    }

    private function channelAdminService(): ChannelAdminService
    {
        return $this->app->make(ChannelAdminService::class);
    }
}
