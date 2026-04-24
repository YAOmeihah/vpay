<?php
declare(strict_types=1);

namespace app\controller\install;

use app\BaseController;
use app\service\install\InstallStateService;
use think\Response;
use think\facade\View;

class Wizard extends BaseController
{
    public function index(): Response
    {
        $state = $this->state();

        return $this->htmlResponse(View::fetch('install/entry', [
            'title' => '安装向导',
            'state' => $state['state'],
            'message' => $state['message'],
            'actions' => $this->actionsFor((string) $state['state']),
        ]));
    }

    public function check(): Response
    {
        return $this->htmlResponse(View::fetch('install/check', [
            'title' => '环境检查',
            'checks' => $this->environmentChecks(),
        ]));
    }

    public function run(): Response
    {
        return $this->htmlResponse(View::fetch('install/progress', [
            'title' => '执行中',
            'steps' => [],
            'message' => '执行尚未接入',
        ]));
    }

    public function recover(): Response
    {
        return $this->htmlResponse(View::fetch('install/recover', [
            'title' => '恢复',
            'context' => $this->recoveryContext(),
        ]));
    }

    protected function state(): array
    {
        return $this->app->make(InstallStateService::class)->status();
    }

    protected function recoveryContext(): array
    {
        return ['step' => '', 'message' => '暂无恢复信息'];
    }

    protected function environmentChecks(): array
    {
        return [
            ['label' => 'PHP >= 8.2', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>=')],
            ['label' => 'PDO', 'ok' => extension_loaded('PDO')],
            ['label' => 'pdo_mysql', 'ok' => extension_loaded('pdo_mysql')],
            ['label' => 'curl', 'ok' => extension_loaded('curl')],
            ['label' => 'json', 'ok' => extension_loaded('json')],
            ['label' => 'mbstring', 'ok' => extension_loaded('mbstring')],
        ];
    }

    protected function actionsFor(string $state): array
    {
        return match ($state) {
            'not_installed' => [['href' => '/install/check', 'label' => '开始安装']],
            'upgrade_required' => [['href' => '/install/check', 'label' => '开始升级']],
            'recovery_required', 'locked' => [['href' => '/install/recover', 'label' => '查看恢复信息']],
            default => [],
        };
    }

    private function htmlResponse(string $html): Response
    {
        return response($html)->contentType('text/html; charset=utf-8');
    }
}
