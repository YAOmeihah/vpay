<?php
declare(strict_types=1);

namespace tests;

use app\controller\install\Wizard;

final class InstallWizardControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'last-error.json';
        if (is_file($path)) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_index_renders_entry_page_for_not_installed_state(): void
    {
        $this->app->view->forgetDriver();

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET');
        $request->setLayer('install');
        $request->setController('Wizard');

        $this->app->instance('request', $request);

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

        $this->app->request->setLayer('');
        $this->app->request->setController('');
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

    public function test_run_renders_recovery_page_when_install_step_throws(): void
    {
        $this->app->view->forgetDriver();

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');

        $this->app->instance('request', $request);

        $controller = new class($this->app) extends Wizard {
            protected function handleRun(): array
            {
                throw new \RuntimeException('写入 .env 失败');
            }
        };

        $html = (string) $controller->run()->getContent();

        self::assertStringContainsString('恢复', $html);
        self::assertStringContainsString('写入 .env 失败', $html);
    }
}
