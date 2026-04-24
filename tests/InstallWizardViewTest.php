<?php
declare(strict_types=1);

namespace tests;

use think\facade\View;

final class InstallWizardViewTest extends TestCase
{
    public function test_install_entry_template_renders_state_badge(): void
    {
        $this->app->view->forgetDriver();

        $html = View::fetch('install/entry', [
            'title' => '安装向导',
            'state' => 'not_installed',
            'message' => '系统尚未安装',
            'actions' => [],
        ]);

        self::assertStringContainsString('安装向导', $html);
        self::assertStringContainsString('not_installed', $html);
        self::assertStringContainsString('系统尚未安装', $html);
    }
}
