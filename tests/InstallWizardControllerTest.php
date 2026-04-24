<?php
declare(strict_types=1);

namespace tests;

use app\controller\install\Wizard;

final class InstallWizardControllerTest extends TestCase
{
    public function test_index_renders_entry_page_for_not_installed_state(): void
    {
        $this->app->view->forgetDriver();

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $html = (string) $controller->index()->getContent();

        self::assertStringContainsString('安装向导', $html);
        self::assertStringContainsString('系统尚未安装', $html);
        self::assertStringContainsString('/install/check', $html);
    }

    public function test_recover_renders_last_error_details(): void
    {
        $this->app->view->forgetDriver();

        $controller = new class($this->app) extends Wizard {
            protected function recoveryContext(): array
            {
                return ['step' => 'bootstrap-schema', 'message' => 'SQL 导入失败'];
            }
        };

        $html = (string) $controller->recover()->getContent();

        self::assertStringContainsString('恢复', $html);
        self::assertStringContainsString('SQL 导入失败', $html);
    }
}
