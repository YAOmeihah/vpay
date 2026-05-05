<?php
declare(strict_types=1);

namespace app\controller\install;

use app\BaseController;
use app\model\Setting;
use app\service\install\DatabaseUpgradeService;
use app\service\install\EnvWriter;
use app\service\install\InstallStepService;
use app\service\install\InstallStateService;
use app\service\security\KeyEncryptionService;
use think\Response;
use think\facade\Db;
use think\facade\View;

class Wizard extends BaseController
{
    public function index(): Response
    {
        $state = $this->state();
        $this->ensureInstallerAvailable($state);

        return $this->htmlResponse(View::fetch('install@/entry', [
            'title' => '安装向导',
            'state' => $state['state'],
            'message' => $state['message'],
            'actions' => $this->actionsFor((string) $state['state']),
        ]));
    }

    public function check(): Response
    {
        $state = $this->state();
        $this->ensureInstallerAvailable($state);

        return $this->renderCheck($state, $this->installContext());
    }

    public function run(): Response
    {
        $this->ensureInstallerAvailable($this->state());

        if (!$this->request->isPost()) {
            return $this->htmlResponse(View::fetch('install@/progress', [
                'title' => '执行中',
                'steps' => [],
                'message' => '执行尚未接入',
            ]));
        }

        try {
            $result = $this->handleRun();

            if (($result['status'] ?? '') === 'validation_failed') {
                return $this->renderCheck(
                    $this->state(),
                    (array) ($result['install'] ?? $this->installContext()),
                    isset($result['upgrade']) && is_array($result['upgrade']) ? $result['upgrade'] : null
                );
            }

            if (($result['installed'] ?? false) === true) {
                $this->cleanupCompletedRuntimeState();
                return $this->htmlResponse(View::fetch('install@/success', [
                    'title' => '完成',
                    'result' => $result,
                ]));
            }

            $context = [
                'step' => 'write-env',
                'message' => '配置文件写入失败，请按提示手工复制后重试。',
                'env' => [
                    'path' => (string) (($result['env']['path'] ?? '')),
                    'content' => (string) (($result['env']['content'] ?? '')),
                ],
            ];
            $this->persistRecoveryContext($context);

            return $this->htmlResponse(View::fetch('install@/recover', [
                'title' => '恢复',
                'context' => $context,
            ]));
        } catch (\Throwable $exception) {
            $context = [
                'step' => 'run',
                'message' => $exception->getMessage(),
            ];

            $this->persistRecoveryContext($context);

            return $this->htmlResponse(View::fetch('install@/recover', [
                'title' => '恢复',
                'context' => $context,
            ]));
        }
    }

    public function recover(): Response
    {
        $this->ensureInstallerAvailable($this->state());

        return $this->htmlResponse(View::fetch('install@/recover', [
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
        $path = $this->installRuntimePath() . DIRECTORY_SEPARATOR . 'last-error.json';
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return [
                    'step' => (string) ($decoded['step'] ?? ''),
                    'message' => (string) ($decoded['message'] ?? '暂无恢复信息'),
                    'env' => [
                        'path' => (string) (($decoded['env']['path'] ?? '')),
                        'content' => (string) (($decoded['env']['content'] ?? '')),
                    ],
                ];
            }
        }

        return [
            'step' => '',
            'message' => '暂无恢复信息',
            'env' => [
                'path' => '',
                'content' => '',
            ],
        ];
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

    protected function handleRun(): array
    {
        $state = $this->state();
        if (($state['state'] ?? '') === 'upgrade_required') {
            $errors = $this->validateUpgradePayload();
            if ($errors !== []) {
                return [
                    'installed' => false,
                    'status' => 'validation_failed',
                    'upgrade' => $this->upgradeContextWithErrors($state, $this->environmentChecks(), $errors),
                ];
            }

            $upgrade = $this->upgradeContext($state, $this->environmentChecks());
            $currentVersion = (string) $upgrade['current_version'];
            $targetVersion = (string) $upgrade['target_version'];

            return $this->withExecutionLock(function () use ($currentVersion, $targetVersion, $upgrade): array {
                $appKey = $this->envWriter()->ensureAppKey();
                if (($appKey['written'] ?? false) !== true) {
                    return [
                        'installed' => false,
                        'status' => 'pending_app_key',
                        'env' => [
                            'path' => (string) ($appKey['path'] ?? ''),
                            'content' => (string) ($appKey['content'] ?? ''),
                        ],
                    ];
                }

                $this->migrateLegacySignKey((string) ($appKey['values']['APP_KEY'] ?? ''));
                $this->databaseUpgradeService()->run($currentVersion, $targetVersion);

                return [
                    'installed' => true,
                    'status' => 'upgraded',
                    'from_version' => $currentVersion,
                    'to_version' => $targetVersion,
                    'migrations' => $upgrade['migrations'],
                ];
            });
        }

        $errors = $this->validateInstallPayload();
        if ($errors !== []) {
            return [
                'installed' => false,
                'status' => 'validation_failed',
                'install' => $this->installContextWithErrors($errors),
            ];
        }

        return $this->withExecutionLock(function (): array {
            return $this->app->make(InstallStepService::class)->install([
                'env' => (array) $this->request->post('env', []),
                'admin_user' => (string) $this->request->post('admin_user', ''),
                'admin_pass' => (string) $this->request->post('admin_pass', ''),
            ]);
        });
    }

    private function htmlResponse(string $html): Response
    {
        return response($html)->contentType('text/html; charset=utf-8');
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array{label: string, ok: bool}> $checks
     * @return array{
     *   current_version: string,
     *   target_version: string,
     *   can_run: bool,
     *   migrations: array<int, array{relative_path: string}>,
     *   errors: array<int, string>,
     *   admin_user: string
     * }
     */
    protected function upgradeContext(array $state, array $checks): array
    {
        $currentVersion = (string) ($state['current_version'] ?? Setting::getConfigValue('schema_version'));
        if ($currentVersion === '') {
            $currentVersion = '2.0.0';
        }

        $targetVersion = (string) ($state['target_version'] ?? config('app.ver'));
        $upgrade = $this->databaseUpgradeService()->context($currentVersion, $targetVersion);

        return [
            'current_version' => $upgrade['current_version'],
            'target_version' => $upgrade['target_version'],
            'can_run' => $this->allChecksPassed($checks),
            'migrations' => $upgrade['migrations'],
            'errors' => [],
            'admin_user' => (string) $this->request->post('upgrade_admin_user', ''),
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array{label: string, ok: bool}> $checks
     * @param array<int, string> $errors
     * @return array<string, mixed>
     */
    protected function upgradeContextWithErrors(array $state, array $checks, array $errors): array
    {
        $context = $this->upgradeContext($state, $checks);
        $context['errors'] = $errors;

        return $context;
    }

    /**
     * @param array<int, array{label: string, ok: bool}> $checks
     */
    protected function allChecksPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if (($check['ok'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    protected function databaseUpgradeService(): DatabaseUpgradeService
    {
        return $this->app->make(DatabaseUpgradeService::class);
    }

    protected function envWriter(): EnvWriter
    {
        return $this->app->make(EnvWriter::class);
    }

    /**
     * @return array{
     *   env: array<string, string>,
     *   admin_user: string,
     *   admin_pass: string,
     *   admin_pass_confirm: string,
     *   errors: array<int, string>,
     *   can_run: bool
     * }
     */
    protected function installContext(): array
    {
        $env = (array) $this->request->post('env', []);

        return [
            'env' => [
                'APP_DEBUG' => (string) ($env['APP_DEBUG'] ?? env('APP_DEBUG', 'false')),
                'APP_KEY' => $this->resolveInstallAppKey($env),
                'COOKIE_SECURE' => (string) ($env['COOKIE_SECURE'] ?? env('COOKIE_SECURE', 'true')),
                'CACHE_DRIVER' => (string) ($env['CACHE_DRIVER'] ?? env('CACHE_DRIVER', 'file')),
                'CACHE_REDIS_HOST' => (string) ($env['CACHE_REDIS_HOST'] ?? env('CACHE_REDIS_HOST', '127.0.0.1')),
                'CACHE_REDIS_PORT' => (string) ($env['CACHE_REDIS_PORT'] ?? env('CACHE_REDIS_PORT', '6379')),
                'CACHE_REDIS_PASSWORD' => '',
                'CACHE_REDIS_SELECT' => (string) ($env['CACHE_REDIS_SELECT'] ?? env('CACHE_REDIS_SELECT', '0')),
                'CACHE_REDIS_TIMEOUT' => (string) ($env['CACHE_REDIS_TIMEOUT'] ?? env('CACHE_REDIS_TIMEOUT', '0')),
                'CACHE_REDIS_PREFIX' => (string) ($env['CACHE_REDIS_PREFIX'] ?? env('CACHE_REDIS_PREFIX', 'vmq_')),
                'CACHE_REDIS_PERSISTENT' => (string) ($env['CACHE_REDIS_PERSISTENT'] ?? env('CACHE_REDIS_PERSISTENT', 'false')),
                'SESSION_TYPE' => (string) ($env['SESSION_TYPE'] ?? env('SESSION_TYPE', 'cache')),
                'SESSION_STORE' => (string) ($env['SESSION_STORE'] ?? env('SESSION_STORE', '')),
                'DB_TYPE' => (string) ($env['DB_TYPE'] ?? env('DB_TYPE', 'mysql')),
                'DB_HOST' => (string) ($env['DB_HOST'] ?? env('DB_HOST', '127.0.0.1')),
                'DB_NAME' => (string) ($env['DB_NAME'] ?? env('DB_NAME', '')),
                'DB_USER' => (string) ($env['DB_USER'] ?? env('DB_USER', 'root')),
                'DB_PASS' => '',
                'DB_PORT' => (string) ($env['DB_PORT'] ?? env('DB_PORT', '3306')),
                'DB_CHARSET' => (string) ($env['DB_CHARSET'] ?? env('DB_CHARSET', 'utf8mb4')),
                'DEFAULT_LANG' => (string) ($env['DEFAULT_LANG'] ?? env('DEFAULT_LANG', 'zh-cn')),
            ],
            'admin_user' => (string) $this->request->post('admin_user', ''),
            'admin_pass' => '',
            'admin_pass_confirm' => '',
            'errors' => [],
            'can_run' => $this->allChecksPassed($this->environmentChecks()),
        ];
    }

    /**
     * @param array<int, string> $errors
     * @return array{
     *   env: array<string, string>,
     *   admin_user: string,
     *   admin_pass: string,
     *   admin_pass_confirm: string,
     *   errors: array<int, string>,
     *   can_run: bool
     * }
     */
    protected function installContextWithErrors(array $errors): array
    {
        $context = $this->installContext();
        $context['errors'] = $errors;
        $context['admin_pass'] = '';
        $context['admin_pass_confirm'] = '';

        return $context;
    }

    /**
     * @return array<int, string>
     */
    protected function validateInstallPayload(): array
    {
        $env = (array) $this->request->post('env', []);
        $errors = [];

        foreach ([
            'DB_HOST' => '数据库主机不能为空',
            'DB_NAME' => '数据库名称不能为空',
            'DB_USER' => '数据库账号不能为空',
            'DB_PORT' => '数据库端口不能为空',
            'DB_CHARSET' => '数据库字符集不能为空',
        ] as $key => $message) {
            if (trim((string) ($env[$key] ?? '')) === '') {
                $errors[] = $message;
            }
        }

        $appKey = trim((string) ($env['APP_KEY'] ?? ''));
        if (!KeyEncryptionService::isValidAppKey($appKey)) {
            $errors[] = 'APP_KEY 格式无效，需为 64 位十六进制字符串';
        }

        $cacheDriver = trim((string) ($env['CACHE_DRIVER'] ?? 'file'));
        if (!in_array($cacheDriver, ['file', 'redis'], true)) {
            $errors[] = '缓存驱动仅支持 file 或 redis';
        }

        $cookieSecure = trim((string) ($env['COOKIE_SECURE'] ?? 'true'));
        if (!in_array($cookieSecure, ['true', 'false', '1', '0'], true)) {
            $errors[] = 'COOKIE_SECURE 仅支持 true 或 false';
        }

        $sessionType = trim((string) ($env['SESSION_TYPE'] ?? 'cache'));
        if (!in_array($sessionType, ['file', 'cache'], true)) {
            $errors[] = 'Session 驱动仅支持 file 或 cache';
        }

        if (trim((string) $this->request->post('admin_user', '')) === '') {
            $errors[] = '管理员账号不能为空';
        }

        $adminPass = (string) $this->request->post('admin_pass', '');
        $adminPassConfirm = (string) $this->request->post('admin_pass_confirm', '');

        if ($adminPass === '') {
            $errors[] = '管理员密码不能为空';
        }

        if ($adminPass !== $adminPassConfirm) {
            $errors[] = '管理员密码与确认密码不一致';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $env
     */
    private function resolveInstallAppKey(array $env): string
    {
        $candidate = (string) ($env['APP_KEY'] ?? env('APP_KEY', ''));
        if (KeyEncryptionService::isValidAppKey($candidate)) {
            return trim($candidate);
        }

        return KeyEncryptionService::generateAppKey();
    }

    private function migrateLegacySignKey(string $appKey): void
    {
        $storedKey = Setting::getConfigValue('key');
        $encryption = new KeyEncryptionService($appKey);
        if ($storedKey === '' || $encryption->isEncrypted($storedKey)) {
            return;
        }

        if (!Setting::setConfigValue('key', $encryption->encrypt($storedKey))) {
            throw new \RuntimeException('旧版签名密钥迁移失败');
        }
    }

    /**
     * @return array<int, string>
     */
    protected function validateUpgradePayload(): array
    {
        $adminUser = trim((string) $this->request->post('upgrade_admin_user', ''));
        $adminPass = (string) $this->request->post('upgrade_admin_pass', '');

        if ($adminUser === '' || $adminPass === '') {
            return ['请输入管理员账号和密码后再执行升级'];
        }

        $storedUser = $this->settingValue('user');
        $storedHash = $this->settingValue('pass');
        if ($adminUser !== $storedUser || $storedHash === '' || !password_verify($adminPass, $storedHash)) {
            return ['管理员账号或密码不正确'];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function ensureInstallerAvailable(array $state): void
    {
        if (($state['state'] ?? '') === 'installed') {
            abort(404, 'Not Found');
        }
    }

    private function installRuntimePath(): string
    {
        return app()->getRuntimePath() . 'install';
    }

    /**
     * @param array<string, mixed> $state
     * @param array{
     *   env: array<string, string>,
     *   admin_user: string,
     *   admin_pass: string,
     *   admin_pass_confirm: string,
     *   errors: array<int, string>,
     *   can_run: bool
     * } $install
     */
    private function renderCheck(array $state, array $install, ?array $upgradeOverride = null): Response
    {
        $checks = $this->environmentChecks();

        return $this->htmlResponse(View::fetch('install@/check', [
            'title' => ($state['state'] ?? '') === 'upgrade_required' ? '升级检查' : '环境检查',
            'state' => $state,
            'checks' => $checks,
            'upgrade' => $upgradeOverride ?? (
                ($state['state'] ?? '') === 'upgrade_required'
                    ? $this->upgradeContext($state, $checks)
                    : []
            ),
            'install' => $install,
        ]));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function persistRecoveryContext(array $context): void
    {
        $runtimePath = $this->installRuntimePath();
        if (!is_dir($runtimePath)) {
            @mkdir($runtimePath, 0777, true);
        }

        @file_put_contents(
            $runtimePath . DIRECTORY_SEPARATOR . 'last-error.json',
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    private function cleanupCompletedRuntimeState(): void
    {
        foreach (['enable.flag', 'last-error.json'] as $file) {
            $path = $this->installRuntimePath() . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withExecutionLock(callable $callback)
    {
        $runtimePath = $this->installRuntimePath();
        if (!is_dir($runtimePath)) {
            @mkdir($runtimePath, 0777, true);
        }

        $lockPath = $runtimePath . DIRECTORY_SEPARATOR . 'lock.json';
        $handle = @fopen($lockPath, 'x');
        if ($handle === false) {
            throw new \RuntimeException('安装或升级正在执行，请稍后重试');
        }

        try {
            fwrite($handle, json_encode([
                'started_at' => time(),
                'action' => 'install-run',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
            fclose($handle);
            $handle = null;

            return $callback();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($lockPath)) {
                @unlink($lockPath);
            }
        }
    }

    private function settingValue(string $key): string
    {
        try {
            $value = Db::name('setting')->where('vkey', $key)->value('vvalue');
        } catch (\Throwable) {
            return '';
        }

        return $value === null ? '' : (string) $value;
    }
}
