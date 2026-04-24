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

    public function test_install_entry_template_renders_trusted_wizard_shell(): void
    {
        $this->app->view->forgetDriver();

        $html = View::fetch('install/entry', [
            'title' => '安装向导',
            'state' => 'not_installed',
            'message' => '系统尚未安装',
            'actions' => [
                ['href' => '/install/check', 'label' => '开始安装'],
            ],
        ]);

        self::assertStringContainsString('class="installer-shell"', $html);
        self::assertStringContainsString('VPay 安装向导', $html);
        self::assertStringContainsString('data-install-state="not_installed"', $html);
        self::assertStringContainsString('系统尚未安装', $html);
        self::assertStringContainsString('开始安装', $html);
        self::assertStringContainsString('安装前会先检查服务器环境', $html);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);
        self::assertStringContainsString('@media (max-width: 760px)', $html);
        self::assertStringContainsString('querySelectorAll', $html);
        self::assertStringContainsString('data-password-toggle', $html);
        self::assertStringContainsString('data-copy-target', $html);
    }
}
