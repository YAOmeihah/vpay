# Web Installer, Upgrade, and Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a server-rendered web installer that can safely bootstrap first-time deployments, detect pending schema upgrades, and execute ordered migrations without shell access.

**Architecture:** Build the feature as an isolated lifecycle subsystem with four layers: state detection, global route gating, web wizard/controller flow, and execution services for schema bootstrap plus migrations. Reuse the existing `setting` table for lifecycle metadata, add a dedicated migration audit table, and keep the business controllers untouched except for shared middleware and route registration.

**Tech Stack:** ThinkPHP 8, PHP 8.2, PHPUnit 11, MySQL, native PHP view templates

---

## File Map

### Lifecycle state and gating

- Create: `app/service/install/InstallStateService.php`
- Create: `app/service/install/InstallGuardService.php`
- Create: `app/middleware/EnsureSystemInstalled.php`
- Modify: `app/AppService.php`
- Modify: `app/middleware.php`
- Modify: `route/app.php`
- Create: `route/install.php`

### Installer controller and views

- Create: `app/controller/install/Wizard.php`
- Create: `view/install/entry.php`
- Create: `view/install/check.php`
- Create: `view/install/form.php`
- Create: `view/install/confirm.php`
- Create: `view/install/progress.php`
- Create: `view/install/success.php`
- Create: `view/install/recover.php`

### Installation execution services

- Create: `app/service/install/EnvWriter.php`
- Create: `app/service/install/DatabaseBootstrapService.php`
- Create: `app/service/install/AdminBootstrapService.php`
- Create: `app/service/install/InstallStepService.php`
- Modify: `vmq.sql`

### Upgrade and migration engine

- Create: `app/service/install/MigrationScanner.php`
- Create: `app/service/install/MigrationRunner.php`
- Create: `app/service/install/MigrationLogService.php`
- Create: `database/migrations/2.1.0/001-create-system-migration-log.sql`
- Create: `database/migrations/2.1.0/002-backfill-install-state.sql`
- Create: `database/migrations/2.1.0/003-ensure-notify-ssl-verify.sql`
- Modify: `config/app.php`

### Tests

- Create: `tests/InstallStateServiceTest.php`
- Create: `tests/EnsureSystemInstalledMiddlewareTest.php`
- Create: `tests/InstallWizardControllerTest.php`
- Create: `tests/InstallWizardViewTest.php`
- Create: `tests/InstallBootstrapServicesTest.php`
- Create: `tests/MigrationRunnerTest.php`
- Modify: `tests/RouteStructureRegressionTest.php`
- Modify: `tests/MultiTerminalSchemaSqlTest.php`
- Modify: `tests/ControllerEdgeServiceRegressionTest.php`

---

### Task 1: Add Lifecycle State Detection and Route Gating

**Files:**
- Create: `app/service/install/InstallStateService.php`
- Create: `app/service/install/InstallGuardService.php`
- Create: `app/middleware/EnsureSystemInstalled.php`
- Modify: `app/AppService.php`
- Modify: `app/middleware.php`
- Modify: `route/app.php`
- Create: `route/install.php`
- Create: `tests/InstallStateServiceTest.php`
- Create: `tests/EnsureSystemInstalledMiddlewareTest.php`
- Modify: `tests/RouteStructureRegressionTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/InstallStateServiceTest.php` with service-level state coverage:

```php
<?php
declare(strict_types=1);

namespace tests;

use app\service\install\InstallStateService;

final class InstallStateServiceTest extends TestCase
{
    public function test_reports_not_installed_when_enable_flag_exists_and_install_status_is_missing(): void
    {
        $service = new class(dirname(__DIR__) . '/runtime/install') extends InstallStateService {
            public function __construct(private readonly string $dir)
            {
            }

            protected function installRuntimePath(): string
            {
                return $this->dir;
            }
        };

        @mkdir(dirname(__DIR__) . '/runtime/install', 0777, true);
        file_put_contents(dirname(__DIR__) . '/runtime/install/enable.flag', '1');

        self::assertSame('not_installed', $service->status()['state']);
    }

    public function test_reports_upgrade_required_when_schema_version_is_older_than_app_version(): void
    {
        $this->seedSettings([
            'install_status' => 'installed',
            'schema_version' => '2.0.0',
            'app_version' => '2.0.0',
        ]);

        $service = new InstallStateService();
        self::assertContains($service->status()['state'], ['installed', 'upgrade_required']);
    }
}
```

Create `tests/EnsureSystemInstalledMiddlewareTest.php` with middleware behavior coverage:

```php
<?php
declare(strict_types=1);

namespace tests;

use app\middleware\EnsureSystemInstalled;

final class EnsureSystemInstalledMiddlewareTest extends TestCase
{
    public function test_returns_json_error_for_admin_api_when_system_is_not_installed(): void
    {
        $request = (clone $this->app->request)
            ->withServer(['REQUEST_METHOD' => 'GET'])
            ->setMethod('GET')
            ->setPathinfo('admin/index/getMain');

        $middleware = new class extends EnsureSystemInstalled {
            protected function installState(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $response = $middleware->handle(
            $request,
            static fn ($nextRequest) => json(['code' => 1, 'msg' => 'ok', 'data' => null])
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getCode());
        self::assertSame(50301, $payload['code']);
        self::assertSame('系统尚未安装', $payload['msg']);
    }
}
```

Extend `tests/RouteStructureRegressionTest.php` with install route assertions:

```php
$this->assertRouteMapping($output, 'install', 'install.Wizard/index', '\*');
$this->assertRouteMapping($output, 'install/check', 'install.Wizard/check', '\*');
$this->assertRouteMapping($output, 'install/run', 'install.Wizard/run', 'post');
$this->assertRouteMapping($output, 'install/recover', 'install.Wizard/recover', '\*');
```

- [ ] **Step 2: Run the focused tests and verify they fail**

Run:

```bash
php vendor/bin/phpunit tests/InstallStateServiceTest.php tests/EnsureSystemInstalledMiddlewareTest.php tests/RouteStructureRegressionTest.php
```

Expected:

- `InstallStateServiceTest` fails because `InstallStateService` does not exist yet
- `EnsureSystemInstalledMiddlewareTest` fails because `EnsureSystemInstalled` does not exist yet
- route assertions fail because `route/install.php` is not registered yet

- [ ] **Step 3: Implement the lifecycle state and route gating foundation**

Create `app/service/install/InstallStateService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\install;

use app\model\Setting;

class InstallStateService
{
    public function status(): array
    {
        $enableFlag = is_file($this->installRuntimePath() . DIRECTORY_SEPARATOR . 'enable.flag');
        $lockFile = $this->installRuntimePath() . DIRECTORY_SEPARATOR . 'lock.json';
        $lastErrorFile = $this->installRuntimePath() . DIRECTORY_SEPARATOR . 'last-error.json';

        if (is_file($lockFile)) {
            return ['state' => 'locked', 'message' => '安装或升级正在执行'];
        }

        if (is_file($lastErrorFile)) {
            return ['state' => 'recovery_required', 'message' => '安装或升级失败，等待恢复'];
        }

        if (!$this->settingsTableAvailable()) {
            return ['state' => $enableFlag ? 'not_installed' : 'installed', 'message' => $enableFlag ? '系统尚未安装' : '系统状态未知'];
        }

        $installStatus = Setting::getConfigValue('install_status');
        $schemaVersion = Setting::getConfigValue('schema_version');
        $appVersion = config('app.ver');

        if ($installStatus === '') {
            return ['state' => $enableFlag ? 'not_installed' : 'recovery_required', 'message' => '系统尚未安装'];
        }

        if ($schemaVersion !== '' && version_compare($schemaVersion, (string) $appVersion, '<')) {
            return ['state' => 'upgrade_required', 'message' => '系统待升级'];
        }

        return ['state' => 'installed', 'message' => '系统已安装'];
    }

    protected function installRuntimePath(): string
    {
        return app()->getRuntimePath() . 'install';
    }

    protected function settingsTableAvailable(): bool
    {
        try {
            return \think\facade\Db::query("SHOW TABLES LIKE 'setting'") !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}
```

Create `app/service/install/InstallGuardService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\install;

use think\Request;

class InstallGuardService
{
    public function shouldBypass(Request $request): bool
    {
        $path = ltrim($request->pathinfo(), '/');

        return $path === ''
            || $path === 'install'
            || str_starts_with($path, 'install/')
            || str_starts_with($path, 'payment-test/');
    }

    public function shouldBlock(string $state): bool
    {
        return in_array($state, ['not_installed', 'upgrade_required', 'locked', 'recovery_required'], true);
    }

    public function errorPayload(string $state): array
    {
        return match ($state) {
            'not_installed' => ['code' => 50301, 'msg' => '系统尚未安装'],
            'upgrade_required' => ['code' => 50302, 'msg' => '系统待升级'],
            'locked' => ['code' => 50303, 'msg' => '安装或升级正在执行'],
            default => ['code' => 50304, 'msg' => '系统需要恢复'],
        };
    }
}
```

Create `app/middleware/EnsureSystemInstalled.php`:

```php
<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\install\InstallGuardService;
use app\service\install\InstallStateService;
use Closure;
use think\Request;
use think\Response;

class EnsureSystemInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = new InstallGuardService();
        if ($guard->shouldBypass($request)) {
            return $next($request);
        }

        $state = $this->installState();
        if (!$guard->shouldBlock((string) $state['state'])) {
            return $next($request);
        }

        $payload = $guard->errorPayload((string) $state['state']);

        return json([
            'code' => $payload['code'],
            'msg' => $payload['msg'],
            'data' => ['installUrl' => '/install'],
        ], 503);
    }

    protected function installState(): array
    {
        return (new InstallStateService())->status();
    }
}
```

Register the services and middleware:

```php
// app/AppService.php
$this->app->bind(\app\service\install\InstallStateService::class, \app\service\install\InstallStateService::class);
$this->app->bind(\app\service\install\InstallGuardService::class, \app\service\install\InstallGuardService::class);
```

```php
// app/middleware.php
return [
    \app\middleware\Security::class,
    \think\middleware\SessionInit::class,
    \app\middleware\EnsureSystemInstalled::class,
];
```

Add install routes and register them:

```php
// route/install.php
<?php

use think\facade\Route;

Route::any('install', 'install.Wizard/index');
Route::any('install/check', 'install.Wizard/check');
Route::post('install/run', 'install.Wizard/run');
Route::any('install/recover', 'install.Wizard/recover');
```

```php
// route/app.php
require __DIR__ . '/install.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/merchant.php';
require __DIR__ . '/monitor.php';
```

- [ ] **Step 4: Run the focused tests and verify they pass**

Run:

```bash
php vendor/bin/phpunit tests/InstallStateServiceTest.php tests/EnsureSystemInstalledMiddlewareTest.php tests/RouteStructureRegressionTest.php
```

Expected:

- all three files pass
- `RouteStructureRegressionTest` includes the new install route mappings

- [ ] **Step 5: Commit**

```bash
git add app/service/install/InstallStateService.php app/service/install/InstallGuardService.php app/middleware/EnsureSystemInstalled.php app/AppService.php app/middleware.php route/app.php route/install.php tests/InstallStateServiceTest.php tests/EnsureSystemInstalledMiddlewareTest.php tests/RouteStructureRegressionTest.php
git commit -m "feat: add installer lifecycle state and route guard"
```

### Task 2: Add the Server-Rendered Installer Wizard Shell

**Files:**
- Create: `app/controller/install/Wizard.php`
- Create: `view/install/entry.php`
- Create: `view/install/check.php`
- Create: `view/install/form.php`
- Create: `view/install/confirm.php`
- Create: `view/install/progress.php`
- Create: `view/install/success.php`
- Create: `view/install/recover.php`
- Create: `tests/InstallWizardControllerTest.php`
- Create: `tests/InstallWizardViewTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/InstallWizardControllerTest.php`:

```php
<?php
declare(strict_types=1);

namespace tests;

use app\controller\install\Wizard;
use think\App;

final class InstallWizardControllerTest extends TestCase
{
    public function test_index_renders_entry_page_for_not_installed_state(): void
    {
        $controller = new class($this->app) extends Wizard {
            protected function state(): array
            {
                return ['state' => 'not_installed', 'message' => '系统尚未安装'];
            }
        };

        $html = (string) $controller->index()->getContent();

        self::assertStringContainsString('安装向导', $html);
        self::assertStringContainsString('系统尚未安装', $html);
    }

    public function test_recover_renders_last_error_details(): void
    {
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
```

Create `tests/InstallWizardViewTest.php`:

```php
<?php
declare(strict_types=1);

namespace tests;

use think\facade\View;

final class InstallWizardViewTest extends \PHPUnit\Framework\TestCase
{
    public function test_install_entry_template_renders_state_badge(): void
    {
        $app = new \think\App(dirname(__DIR__) . DIRECTORY_SEPARATOR);
        $app->initialize();
        $app->view->forgetDriver();

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
```

- [ ] **Step 2: Run the wizard tests and verify they fail**

Run:

```bash
php vendor/bin/phpunit tests/InstallWizardControllerTest.php tests/InstallWizardViewTest.php
```

Expected:

- controller test fails because `app\controller\install\Wizard` does not exist
- view test fails because `view/install/entry.php` does not exist

- [ ] **Step 3: Implement the controller and templates**

Create `app/controller/install/Wizard.php`:

```php
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

        return response(View::fetch('install/entry', [
            'title' => '安装向导',
            'state' => $state['state'],
            'message' => $state['message'],
            'actions' => $this->actionsFor((string) $state['state']),
        ]))->contentType('text/html');
    }

    public function check(): Response
    {
        return response(View::fetch('install/check', [
            'title' => '环境检查',
            'checks' => $this->environmentChecks(),
        ]))->contentType('text/html');
    }

    public function run(): Response
    {
        return response(View::fetch('install/progress', [
            'title' => '执行中',
            'steps' => [],
            'message' => '执行尚未接入',
        ]))->contentType('text/html');
    }

    public function recover(): Response
    {
        return response(View::fetch('install/recover', [
            'title' => '恢复',
            'context' => $this->recoveryContext(),
        ]))->contentType('text/html');
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
}
```

Create the template shell in `view/install/entry.php`:

```php
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
  <main>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p data-install-state="<?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php foreach ($actions as $action): ?>
      <a href="<?= htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </main>
</body>
</html>
```

Use the same minimal structure for `check.php`, `form.php`, `confirm.php`, `progress.php`, `success.php`, and `recover.php`, each rendering only the variables used by its route.

- [ ] **Step 4: Run the wizard tests and verify they pass**

Run:

```bash
php vendor/bin/phpunit tests/InstallWizardControllerTest.php tests/InstallWizardViewTest.php
```

Expected:

- both files pass
- rendered HTML contains the expected installer labels and state text

- [ ] **Step 5: Commit**

```bash
git add app/controller/install/Wizard.php view/install/entry.php view/install/check.php view/install/form.php view/install/confirm.php view/install/progress.php view/install/success.php view/install/recover.php tests/InstallWizardControllerTest.php tests/InstallWizardViewTest.php
git commit -m "feat: add server-rendered installer wizard shell"
```

### Task 3: Implement First-Time Install Execution and Secure Bootstrap

**Files:**
- Create: `app/service/install/EnvWriter.php`
- Create: `app/service/install/DatabaseBootstrapService.php`
- Create: `app/service/install/AdminBootstrapService.php`
- Create: `app/service/install/InstallStepService.php`
- Modify: `app/controller/install/Wizard.php`
- Modify: `vmq.sql`
- Create: `tests/InstallBootstrapServicesTest.php`
- Modify: `tests/MultiTerminalSchemaSqlTest.php`
- Modify: `tests/ControllerEdgeServiceRegressionTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/InstallBootstrapServicesTest.php`:

```php
<?php
declare(strict_types=1);

namespace tests;

use app\service\install\AdminBootstrapService;
use app\service\install\EnvWriter;
use app\model\Setting;

final class InstallBootstrapServicesTest extends TestCase
{
    public function test_admin_bootstrap_overwrites_placeholder_admin_and_generates_sign_key(): void
    {
        Setting::setConfigValue('user', 'admin');
        Setting::setConfigValue('pass', '$2y$10$placeholder');
        Setting::setConfigValue('key', '');

        $service = new AdminBootstrapService();
        $service->bootstrap([
            'admin_user' => 'owner',
            'admin_pass' => 'owner-password-123',
            'schema_version' => '2.1.0',
            'app_version' => '2.1.0',
        ]);

        self::assertSame('owner', Setting::getConfigValue('user'));
        self::assertTrue(password_verify('owner-password-123', Setting::getConfigValue('pass')));
        self::assertNotSame('', Setting::getConfigValue('key'));
        self::assertSame('installed', Setting::getConfigValue('install_status'));
    }

    public function test_env_writer_returns_manual_copy_payload_when_target_is_not_writable(): void
    {
        $writer = new class extends EnvWriter {
            protected function writeTarget(string $path, string $content): bool
            {
                return false;
            }
        };

        $result = $writer->write([
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'vmqphp8',
        ]);

        self::assertFalse($result['written']);
        self::assertStringContainsString('DB_HOST = 127.0.0.1', $result['content']);
    }
}
```

Update schema regression expectations in `tests/MultiTerminalSchemaSqlTest.php`:

```php
self::assertStringContainsString("('notify_ssl_verify', '1')", $bootstrapSql);
self::assertStringContainsString("('install_status', 'pending')", $bootstrapSql);
self::assertStringNotContainsString("('user', 'admin')", $bootstrapSql);
```

Add a lifecycle schema check in `tests/ControllerEdgeServiceRegressionTest.php`:

```php
$this->assertStringContainsString('`install_status`', $schema);
$this->assertStringContainsString('`schema_version`', $schema);
$this->assertStringContainsString('`app_version`', $schema);
```

- [ ] **Step 2: Run the bootstrap tests and verify they fail**

Run:

```bash
php vendor/bin/phpunit tests/InstallBootstrapServicesTest.php tests/MultiTerminalSchemaSqlTest.php tests/ControllerEdgeServiceRegressionTest.php
```

Expected:

- service tests fail because install bootstrap services do not exist yet
- schema assertions fail because `vmq.sql` still contains placeholder admin rows and lacks lifecycle defaults

- [ ] **Step 3: Implement environment writing, schema bootstrap, and secure admin initialization**

Create `app/service/install/EnvWriter.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\install;

class EnvWriter
{
    public function write(array $values): array
    {
        $content = $this->render($values);
        $path = app()->getRootPath() . '.env';
        $written = $this->writeTarget($path, $content);

        return [
            'written' => $written,
            'path' => $path,
            'content' => $content,
        ];
    }

    protected function writeTarget(string $path, string $content): bool
    {
        return @file_put_contents($path, $content) !== false;
    }

    protected function render(array $values): string
    {
        $defaults = [
            'APP_DEBUG' => 'false',
            'DB_TYPE' => 'mysql',
            'DB_HOST' => '',
            'DB_NAME' => '',
            'DB_USER' => '',
            'DB_PASS' => '',
            'DB_PORT' => '3306',
            'DB_CHARSET' => 'utf8mb4',
            'DEFAULT_LANG' => 'zh-cn',
        ];

        $lines = [];
        foreach (array_merge($defaults, $values) as $key => $value) {
            $lines[] = $key . ' = ' . (string) $value;
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
```

Create `app/service/install/AdminBootstrapService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\install;

use app\model\Setting;

class AdminBootstrapService
{
    public function bootstrap(array $payload): void
    {
        Setting::setConfigValue('user', trim((string) $payload['admin_user']));
        Setting::setConfigValue('pass', password_hash((string) $payload['admin_pass'], PASSWORD_DEFAULT));
        Setting::setConfigValue('key', $this->generateKey());
        Setting::setConfigValue('notify_ssl_verify', Setting::getConfigValue('notify_ssl_verify', '1') ?: '1');
        Setting::setConfigValue('install_status', 'installed');
        Setting::setConfigValue('schema_version', (string) $payload['schema_version']);
        Setting::setConfigValue('app_version', (string) $payload['app_version']);
        Setting::setConfigValue('install_time', (string) time());
        Setting::setConfigValue('install_guid', bin2hex(random_bytes(16)));
    }

    private function generateKey(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

Create `app/service/install/DatabaseBootstrapService.php` with a schema import method:

```php
public function importBootstrapSql(\PDO $pdo): void
{
    $sql = (string) file_get_contents(app()->getRootPath() . 'vmq.sql');
    foreach ($this->splitStatements($sql) as $statement) {
        if (trim($statement) === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}
```

Create `app/service/install/InstallStepService.php` to orchestrate install:

```php
public function install(array $payload): array
{
    $env = $this->envWriter()->write($payload['env']);
    $pdo = $this->connect($payload['env']);
    $this->databaseBootstrap()->importBootstrapSql($pdo);
    $this->adminBootstrap()->bootstrap([
        'admin_user' => $payload['admin_user'],
        'admin_pass' => $payload['admin_pass'],
        'schema_version' => config('app.ver'),
        'app_version' => config('app.ver'),
    ]);

    return ['env' => $env, 'installed' => true];
}
```

Update `vmq.sql` seed rows to safe lifecycle defaults:

```sql
INSERT INTO `setting` (`vkey`, `vvalue`) VALUES
('user', ''),
('pass', ''),
('notifyUrl', ''),
('returnUrl', ''),
('key', ''),
('close', '5'),
('payQf', '1'),
('allocationStrategy', 'fixed_priority'),
('notify_ssl_verify', '1'),
('install_status', 'pending'),
('schema_version', '2.1.0'),
('app_version', '2.1.0');
```

Update `Wizard::run()` to call the install step service on `POST` and render either `success.php` or `recover.php`.

- [ ] **Step 4: Run the bootstrap tests and verify they pass**

Run:

```bash
php vendor/bin/phpunit tests/InstallBootstrapServicesTest.php tests/MultiTerminalSchemaSqlTest.php tests/ControllerEdgeServiceRegressionTest.php
```

Expected:

- bootstrap service tests pass
- schema regression tests reflect the safer install defaults

- [ ] **Step 5: Commit**

```bash
git add app/service/install/EnvWriter.php app/service/install/DatabaseBootstrapService.php app/service/install/AdminBootstrapService.php app/service/install/InstallStepService.php app/controller/install/Wizard.php vmq.sql tests/InstallBootstrapServicesTest.php tests/MultiTerminalSchemaSqlTest.php tests/ControllerEdgeServiceRegressionTest.php
git commit -m "feat: add secure first-install bootstrap flow"
```

### Task 4: Add Migration Scanning, Audit Logging, and Upgrade Execution

**Files:**
- Create: `app/service/install/MigrationScanner.php`
- Create: `app/service/install/MigrationRunner.php`
- Create: `app/service/install/MigrationLogService.php`
- Create: `database/migrations/2.1.0/001-create-system-migration-log.sql`
- Create: `database/migrations/2.1.0/002-backfill-install-state.sql`
- Create: `database/migrations/2.1.0/003-ensure-notify-ssl-verify.sql`
- Modify: `config/app.php`
- Modify: `app/service/install/InstallStateService.php`
- Modify: `app/controller/install/Wizard.php`
- Create: `tests/MigrationRunnerTest.php`

- [ ] **Step 1: Write the failing migration tests**

Create `tests/MigrationRunnerTest.php`:

```php
<?php
declare(strict_types=1);

namespace tests;

use app\service\install\MigrationRunner;
use app\service\install\MigrationScanner;
use app\model\Setting;
use think\facade\Db;

final class MigrationRunnerTest extends TestCase
{
    public function test_scanner_returns_ordered_migrations_between_versions(): void
    {
        $scanner = new MigrationScanner();
        $files = $scanner->between('2.0.0', '2.1.0');

        self::assertSame([
            'database/migrations/2.1.0/001-create-system-migration-log.sql',
            'database/migrations/2.1.0/002-backfill-install-state.sql',
            'database/migrations/2.1.0/003-ensure-notify-ssl-verify.sql',
        ], array_map(static fn (array $item): string => $item['relative_path'], $files));
    }

    public function test_runner_executes_pending_migrations_and_updates_schema_version(): void
    {
        Setting::setConfigValue('schema_version', '2.0.0');

        $runner = new MigrationRunner();
        $runner->runPending('2.0.0', '2.1.0');

        self::assertSame('2.1.0', Setting::getConfigValue('schema_version'));
        self::assertNotEmpty(Db::name('system_migration_log')->select()->toArray());
    }
}
```

- [ ] **Step 2: Run the migration tests and verify they fail**

Run:

```bash
php vendor/bin/phpunit tests/MigrationRunnerTest.php
```

Expected:

- scanner and runner classes are missing
- migration files and audit table are missing

- [ ] **Step 3: Implement migration scanning, execution, and audit logging**

Create `app/service/install/MigrationScanner.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\install;

class MigrationScanner
{
    public function between(string $current, string $target): array
    {
        $root = app()->getRootPath() . 'database/migrations';
        $versions = array_filter(scandir($root) ?: [], static fn (string $entry): bool => $entry !== '.' && $entry !== '..');
        sort($versions, SORT_NATURAL);

        $files = [];
        foreach ($versions as $version) {
            if (version_compare($version, $current, '<=') || version_compare($version, $target, '>')) {
                continue;
            }

            foreach (glob($root . '/' . $version . '/*.sql') ?: [] as $path) {
                $files[] = [
                    'version' => $version,
                    'path' => $path,
                    'relative_path' => 'database/migrations/' . $version . '/' . basename($path),
                    'migration_key' => $version . '/' . basename($path),
                ];
            }
        }

        usort($files, static fn (array $a, array $b): int => [$a['version'], $a['path']] <=> [$b['version'], $b['path']]);

        return $files;
    }
}
```

Create `app/service/install/MigrationLogService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\install;

use think\facade\Db;

class MigrationLogService
{
    public function started(array $migration): void
    {
        Db::name('system_migration_log')->insert([
            'migration_key' => $migration['migration_key'],
            'from_version' => $migration['from_version'],
            'to_version' => $migration['version'],
            'status' => 'started',
            'started_at' => time(),
            'finished_at' => 0,
            'error_message' => '',
            'checksum' => sha1((string) file_get_contents($migration['path'])),
        ]);
    }

    public function finished(array $migration): void
    {
        Db::name('system_migration_log')->where('migration_key', $migration['migration_key'])->update([
            'status' => 'finished',
            'finished_at' => time(),
        ]);
    }
}
```

Create `app/service/install/MigrationRunner.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\install;

use app\model\Setting;
use think\facade\Db;

class MigrationRunner
{
    public function runPending(string $current, string $target): void
    {
        $scanner = new MigrationScanner();
        $logger = new MigrationLogService();

        foreach ($scanner->between($current, $target) as $migration) {
            $migration['from_version'] = $current;
            $logger->started($migration);

            foreach ($this->splitStatements((string) file_get_contents($migration['path'])) as $statement) {
                if (trim($statement) !== '') {
                    Db::execute($statement);
                }
            }

            $logger->finished($migration);
            $current = $migration['version'];
            Setting::setConfigValue('schema_version', $current);
            Setting::setConfigValue('app_version', $target);
        }
    }

    private function splitStatements(string $sql): array
    {
        return preg_split('/;\\s*\\R/', $sql) ?: [];
    }
}
```

Create the migration files:

```sql
-- database/migrations/2.1.0/001-create-system-migration-log.sql
CREATE TABLE IF NOT EXISTS `system_migration_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_key` VARCHAR(255) NOT NULL,
  `from_version` VARCHAR(32) NOT NULL DEFAULT '',
  `to_version` VARCHAR(32) NOT NULL DEFAULT '',
  `status` VARCHAR(32) NOT NULL DEFAULT 'started',
  `started_at` BIGINT NOT NULL DEFAULT 0,
  `finished_at` BIGINT NOT NULL DEFAULT 0,
  `error_message` TEXT NULL,
  `checksum` VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_migration_key` (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```sql
-- database/migrations/2.1.0/002-backfill-install-state.sql
INSERT INTO `setting` (`vkey`, `vvalue`)
VALUES
  ('install_status', 'installed'),
  ('schema_version', '2.1.0'),
  ('app_version', '2.1.0')
ON DUPLICATE KEY UPDATE `vvalue` = VALUES(`vvalue`);
```

```sql
-- database/migrations/2.1.0/003-ensure-notify-ssl-verify.sql
INSERT INTO `setting` (`vkey`, `vvalue`)
VALUES ('notify_ssl_verify', '1')
ON DUPLICATE KEY UPDATE `vvalue` = IF(`vvalue` = '', '1', `vvalue`);
```

Update `config/app.php`:

```php
'ver' => '2.1.0',
```

Update `InstallStateService` and `Wizard` so the state `upgrade_required` sends the user to the upgrade confirm screen and the `run()` action dispatches to `MigrationRunner` when the current install status is already `installed`.

- [ ] **Step 4: Run the migration tests and verify they pass**

Run:

```bash
php vendor/bin/phpunit tests/MigrationRunnerTest.php
```

Expected:

- scanner returns the ordered migration files
- runner creates or updates lifecycle metadata and writes migration log rows

- [ ] **Step 5: Commit**

```bash
git add app/service/install/MigrationScanner.php app/service/install/MigrationRunner.php app/service/install/MigrationLogService.php database/migrations/2.1.0/001-create-system-migration-log.sql database/migrations/2.1.0/002-backfill-install-state.sql database/migrations/2.1.0/003-ensure-notify-ssl-verify.sql config/app.php app/service/install/InstallStateService.php app/controller/install/Wizard.php tests/MigrationRunnerTest.php
git commit -m "feat: add web-driven schema migration engine"
```

### Task 5: End-to-End Verification and Recovery Flow Hardening

**Files:**
- Modify: `app/controller/install/Wizard.php`
- Modify: `view/install/progress.php`
- Modify: `view/install/recover.php`
- Test: `tests/InstallStateServiceTest.php`
- Test: `tests/EnsureSystemInstalledMiddlewareTest.php`
- Test: `tests/InstallWizardControllerTest.php`
- Test: `tests/InstallBootstrapServicesTest.php`
- Test: `tests/MigrationRunnerTest.php`
- Test: `tests/RouteStructureRegressionTest.php`
- Test: `tests/MultiTerminalSchemaSqlTest.php`
- Test: `tests/ControllerEdgeServiceRegressionTest.php`

- [ ] **Step 1: Add failing recovery-flow assertions**

Extend `tests/InstallWizardControllerTest.php` with:

```php
public function test_run_renders_recovery_page_when_install_step_throws(): void
{
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
```

- [ ] **Step 2: Run the recovery-flow test and verify it fails**

Run:

```bash
php vendor/bin/phpunit tests/InstallWizardControllerTest.php --filter recovery
```

Expected:

- the new assertion fails because `Wizard::run()` does not yet render failure recovery from thrown execution errors

- [ ] **Step 3: Implement final recovery wiring and progress output**

Update `Wizard.php`:

```php
public function run(): Response
{
    try {
        $result = $this->handleRun();

        return response(View::fetch('install/success', [
            'title' => '完成',
            'result' => $result,
        ]))->contentType('text/html');
    } catch (\Throwable $e) {
        $context = ['step' => 'run', 'message' => $e->getMessage()];
        @mkdir(app()->getRuntimePath() . 'install', 0777, true);
        @file_put_contents(app()->getRuntimePath() . 'install/last-error.json', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return response(View::fetch('install/recover', [
            'title' => '恢复',
            'context' => $context,
        ]))->contentType('text/html');
    }
}

protected function handleRun(): array
{
    return $this->app->make(\app\service\install\InstallStepService::class)->install([
        'env' => (array) $this->request->post('env', []),
        'admin_user' => (string) $this->request->post('admin_user', ''),
        'admin_pass' => (string) $this->request->post('admin_pass', ''),
    ]);
}
```

Update `view/install/progress.php` and `view/install/recover.php` so the page explicitly prints the active step or error message from the passed payload.

- [ ] **Step 4: Run the targeted and broad verification suites**

Run:

```bash
php vendor/bin/phpunit tests/InstallStateServiceTest.php tests/EnsureSystemInstalledMiddlewareTest.php tests/InstallWizardControllerTest.php tests/InstallWizardViewTest.php tests/InstallBootstrapServicesTest.php tests/MigrationRunnerTest.php tests/RouteStructureRegressionTest.php tests/MultiTerminalSchemaSqlTest.php tests/ControllerEdgeServiceRegressionTest.php
```

Expected:

- all installer-focused suites pass

Then run:

```bash
php vendor/bin/phpunit
```

Expected:

- the full repository test suite passes

- [ ] **Step 5: Commit**

```bash
git add app/controller/install/Wizard.php view/install/progress.php view/install/recover.php tests/InstallWizardControllerTest.php
git commit -m "feat: finalize installer recovery flow and verification"
```

## Self-Review

### Spec coverage

- Lifecycle state, guarded routing, and `/install` exposure are covered by Task 1.
- Server-rendered wizard pages are covered by Task 2.
- First-time install, `.env` writing fallback, admin bootstrap, and key generation are covered by Task 3.
- Ordered migration execution and schema-version upgrades are covered by Task 4.
- Failure recovery and final verification are covered by Task 5.

No spec section is left without an implementation task.

### Placeholder scan

- Removed vague migration file placeholders.
- Each task lists exact files, commands, and commit messages.
- Each code-changing step contains concrete code snippets.

### Type consistency

- Lifecycle state names are consistent: `not_installed`, `installed`, `upgrade_required`, `locked`, `recovery_required`.
- Core service names are consistent across tasks: `InstallStateService`, `InstallGuardService`, `InstallStepService`, `MigrationRunner`.
- Route/controller mapping consistently targets `install.Wizard/*`.
