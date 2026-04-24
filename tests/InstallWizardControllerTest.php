<?php
declare(strict_types=1);

namespace tests;

use app\controller\install\Wizard;
use app\service\install\MigrationRunner;
use think\exception\HttpException;

final class InstallWizardControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $runtimePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'install';
        foreach (['last-error.json', 'enable.flag', 'lock.json'] as $file) {
            $path = $runtimePath . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                @unlink($path);
            }
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

    public function test_check_renders_upgrade_summary_and_execute_form_for_upgrade_state(): void
    {
        $this->app->view->forgetDriver();

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'upgrade_required',
                    'message' => '检测到旧版系统，需要升级',
                    'current_version' => '2.0.0',
                    'target_version' => '2.1.0',
                ];
            }

            protected function environmentChecks(): array
            {
                return [
                    ['label' => 'PHP >= 8.2', 'ok' => true],
                    ['label' => 'pdo_mysql', 'ok' => true],
                ];
            }

            protected function upgradeContext(array $state, array $checks): array
            {
                return [
                    'current_version' => '2.0.0',
                    'target_version' => '2.1.0',
                    'can_run' => true,
                    'migrations' => [
                        ['relative_path' => 'database/migrations/2.1.0/001-create-system-migration-log.sql'],
                        ['relative_path' => 'database/migrations/2.1.0/002-backfill-install-state.sql'],
                    ],
                ];
            }
        };

        $html = (string) $controller->check()->getContent();

        self::assertStringContainsString('当前版本', $html);
        self::assertStringContainsString('2.0.0', $html);
        self::assertStringContainsString('2.1.0', $html);
        self::assertStringContainsString('database/migrations/2.1.0/001-create-system-migration-log.sql', $html);
        self::assertStringContainsString('action="/install/run"', $html);
        self::assertStringContainsString('确认升级并执行', $html);
        self::assertStringContainsString('name="upgrade_admin_user"', $html);
        self::assertStringContainsString('name="upgrade_admin_pass"', $html);
        self::assertStringContainsString('升级前请先完成备份', $html);
        self::assertStringContainsString('2.0.0', $html);
        self::assertStringContainsString('2.1.0', $html);
        self::assertStringContainsString('data-install-form', $html);
        self::assertStringContainsString('data-loading-text="正在升级，请勿刷新"', $html);
        self::assertStringContainsString('data-password-toggle="upgrade-admin-pass"', $html);
        self::assertStringContainsString('待执行 Migration', $html);
    }

    public function test_check_renders_first_install_form_for_not_installed_state(): void
    {
        $this->app->view->forgetDriver();

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'not_installed',
                    'message' => '系统尚未安装',
                ];
            }

            protected function environmentChecks(): array
            {
                return [
                    ['label' => 'PHP >= 8.2', 'ok' => true],
                    ['label' => 'pdo_mysql', 'ok' => true],
                ];
            }

            protected function installContext(): array
            {
                return [
                    'env' => [
                        'APP_DEBUG' => 'true',
                        'DB_TYPE' => 'mysql',
                        'DB_HOST' => '127.0.0.1',
                        'DB_NAME' => 'vmq_install_check',
                        'DB_USER' => 'root',
                        'DB_PASS' => 'root-secret',
                        'DB_PORT' => '3306',
                        'DB_CHARSET' => 'utf8mb4',
                        'DEFAULT_LANG' => 'zh-cn',
                    ],
                    'admin_user' => 'owner',
                    'admin_pass' => '',
                    'admin_pass_confirm' => '',
                    'errors' => [],
                    'can_run' => true,
                ];
            }
        };

        $html = (string) $controller->check()->getContent();

        self::assertStringContainsString('数据库配置', $html);
        self::assertStringContainsString('name="env[DB_HOST]"', $html);
        self::assertStringContainsString('value="127.0.0.1"', $html);
        self::assertStringNotContainsString('root-secret', $html);
        self::assertStringContainsString('name="admin_user"', $html);
        self::assertStringContainsString('name="admin_pass"', $html);
        self::assertStringContainsString('name="admin_pass_confirm"', $html);
        self::assertStringContainsString('action="/install/run"', $html);
        self::assertStringContainsString('确认安装并执行', $html);
        self::assertStringContainsString('环境检查', $html);
        self::assertStringContainsString('数据库连接', $html);
        self::assertStringContainsString('管理员账号', $html);
        self::assertStringContainsString('data-install-form', $html);
        self::assertStringContainsString('data-loading-text="正在安装，请勿刷新"', $html);
        self::assertStringContainsString('data-password-toggle="install-db-pass"', $html);
        self::assertStringContainsString('服务器环境已满足执行条件', $html);
    }

    public function test_check_renders_failed_environment_guidance_for_install_state(): void
    {
        $this->app->view->forgetDriver();

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'not_installed',
                    'message' => '系统尚未安装',
                ];
            }

            protected function environmentChecks(): array
            {
                return [
                    ['label' => 'PHP >= 8.2', 'ok' => true],
                    ['label' => 'pdo_mysql', 'ok' => false],
                ];
            }
        };

        $html = (string) $controller->check()->getContent();

        self::assertStringContainsString('存在未通过的环境项，修复后刷新此页重新检查。', $html);
        self::assertStringContainsString('请先修复失败的 PHP 扩展或版本要求', $html);
        self::assertStringContainsString('disabled', $html);
    }

    public function test_check_does_not_resolve_upgrade_context_for_first_install_state(): void
    {
        $this->app->view->forgetDriver();

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'not_installed',
                    'message' => '系统尚未安装',
                ];
            }

            protected function environmentChecks(): array
            {
                return [
                    ['label' => 'PHP >= 8.2', 'ok' => true],
                ];
            }

            protected function upgradeContext(array $state, array $checks): array
            {
                throw new \RuntimeException('upgrade context should not be resolved during first install');
            }
        };

        $html = (string) $controller->check()->getContent();

        self::assertStringContainsString('数据库配置', $html);
        self::assertStringContainsString('确认安装并执行', $html);
    }

    public function test_index_aborts_with_404_when_system_is_already_installed(): void
    {
        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return ['state' => 'installed', 'message' => '系统已安装'];
            }
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Not Found');

        $controller->index();
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

    public function test_run_renders_install_success_summary_when_first_install_completes(): void
    {
        $this->app->view->forgetDriver();
        $runtimePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'install';
        @mkdir($runtimePath, 0777, true);
        file_put_contents($runtimePath . DIRECTORY_SEPARATOR . 'enable.flag', '1');
        file_put_contents($runtimePath . DIRECTORY_SEPARATOR . 'last-error.json', '{"step":"old"}');

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');
        $this->app->instance('request', $request);

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'not_installed',
                    'message' => '系统尚未安装',
                ];
            }

            protected function handleRun(): array
            {
                return [
                    'installed' => true,
                    'status' => 'installed',
                    'admin_user' => 'owner',
                    'env' => [
                        'path' => '/var/www/vpay/.env',
                    ],
                ];
            }
        };

        $html = (string) $controller->run()->getContent();

        self::assertStringContainsString('安装流程已完成', $html);
        self::assertStringContainsString('owner', $html);
        self::assertStringContainsString('/var/www/vpay/.env', $html);
        self::assertStringContainsString('进入管理后台', $html);
        self::assertStringContainsString('/console/', $html);
        self::assertStringContainsString('安装已完成', $html);
        self::assertFileDoesNotExist($runtimePath . DIRECTORY_SEPARATOR . 'enable.flag');
        self::assertFileDoesNotExist($runtimePath . DIRECTORY_SEPARATOR . 'last-error.json');
    }

    public function test_run_re_renders_install_form_with_validation_errors_when_password_confirmation_mismatches(): void
    {
        $this->app->view->forgetDriver();

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');
        $request->withPost([
            'env' => [
                'DB_HOST' => '127.0.0.1',
                'DB_NAME' => 'vmq_install_check',
                'DB_USER' => 'root',
                'DB_PASS' => 'root',
                'DB_PORT' => '3306',
                'DB_CHARSET' => 'utf8mb4',
                'DEFAULT_LANG' => 'zh-cn',
            ],
            'admin_user' => 'owner',
            'admin_pass' => 'owner-password-123',
            'admin_pass_confirm' => 'different-password',
        ]);
        $this->app->instance('request', $request);

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'not_installed',
                    'message' => '系统尚未安装',
                ];
            }

            protected function environmentChecks(): array
            {
                return [
                    ['label' => 'PHP >= 8.2', 'ok' => true],
                    ['label' => 'pdo_mysql', 'ok' => true],
                ];
            }
        };

        $html = (string) $controller->run()->getContent();

        self::assertStringContainsString('管理员密码与确认密码不一致', $html);
        self::assertStringContainsString('name="admin_pass_confirm"', $html);
        self::assertStringContainsString('value="owner"', $html);
        self::assertStringContainsString('value="vmq_install_check"', $html);
    }

    public function test_run_renders_manual_env_copy_context_when_install_step_returns_pending(): void
    {
        $this->app->view->forgetDriver();

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');
        $this->app->instance('request', $request);

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'not_installed',
                    'message' => '系统尚未安装',
                ];
            }

            protected function handleRun(): array
            {
                return [
                    'installed' => false,
                    'status' => 'pending',
                    'env' => [
                        'path' => '/var/www/vpay/.env',
                        'content' => "DB_HOST = 127.0.0.1\nDB_NAME = vmq_install_check\n",
                    ],
                ];
            }
        };

        $html = (string) $controller->run()->getContent();

        self::assertStringContainsString('手工写入以下内容', $html);
        self::assertStringContainsString('/var/www/vpay/.env', $html);
        self::assertStringContainsString('DB_NAME = vmq_install_check', $html);
        self::assertStringContainsString('复制配置内容', $html);
        self::assertStringContainsString('data-copy-target="manual-env-content"', $html);
        self::assertStringContainsString('返回检查页', $html);
        self::assertStringContainsString('/install/check', $html);
    }

    public function test_run_uses_legacy_schema_baseline_when_upgrade_state_has_no_schema_version(): void
    {
        $this->app->view->forgetDriver();
        $adminPass = 'upgrade-password-123';
        $this->seedSettings([
            'user' => 'admin',
            'pass' => password_hash($adminPass, PASSWORD_DEFAULT),
            'key' => 'legacy-sign-key',
            'notify_ssl_verify' => '1',
        ]);

        $runner = new class extends MigrationRunner {
            public string $fromVersion = '';
            public string $toVersion = '';

            public function runPending(string $current, string $target): void
            {
                $this->fromVersion = $current;
                $this->toVersion = $target;
            }
        };
        $this->app->instance(MigrationRunner::class, $runner);

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');
        $request->withPost([
            'upgrade_admin_user' => 'admin',
            'upgrade_admin_pass' => $adminPass,
        ]);
        $this->app->instance('request', $request);

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'upgrade_required',
                    'message' => '检测到旧版系统，需要升级',
                    'current_version' => '2.0.0',
                ];
            }
        };

        $html = (string) $controller->run()->getContent();

        self::assertStringContainsString('完成', $html);
        self::assertSame('2.0.0', $runner->fromVersion);
        self::assertSame('2.1.1', $runner->toVersion);
    }

    public function test_run_requires_admin_credentials_before_executing_upgrade(): void
    {
        $this->app->view->forgetDriver();
        $this->seedSettings([
            'user' => 'admin',
            'pass' => password_hash('correct-password', PASSWORD_DEFAULT),
            'key' => 'legacy-sign-key',
        ]);

        $runner = new class extends MigrationRunner {
            public bool $called = false;

            public function runPending(string $current, string $target): void
            {
                $this->called = true;
            }
        };
        $this->app->instance(MigrationRunner::class, $runner);

        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'POST'])
            ->setMethod('POST');
        $request->withPost([
            'upgrade_admin_user' => 'admin',
            'upgrade_admin_pass' => 'wrong-password',
        ]);
        $this->app->instance('request', $request);

        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return [
                    'state' => 'upgrade_required',
                    'message' => '检测到旧版系统，需要升级',
                    'current_version' => '2.0.0',
                    'target_version' => '2.1.0',
                ];
            }
        };

        $html = (string) $controller->run()->getContent();

        self::assertFalse($runner->called);
        self::assertStringContainsString('管理员账号或密码不正确', $html);
        self::assertStringContainsString('name="upgrade_admin_user"', $html);
    }
}
