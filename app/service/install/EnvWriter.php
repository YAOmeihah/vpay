<?php
declare(strict_types=1);

namespace app\service\install;

use app\service\security\KeyEncryptionService;
use think\facade\Env as EnvFacade;

class EnvWriter
{
    /**
     * @param array<string, string> $values
     * @return array{written: bool, path: string, content: string, values: array<string, string>}
     */
    public function write(array $values): array
    {
        $resolvedValues = $this->resolveValues($values);
        $content = $this->render($resolvedValues);
        $path = app()->getRootPath() . '.env';
        $written = $this->writeTarget($path, $content);
        if ($written) {
            $this->applyRuntimeAppKey($resolvedValues['APP_KEY']);
        }

        return [
            'written' => $written,
            'path' => $path,
            'content' => $content,
            'values' => $resolvedValues,
        ];
    }

    /**
     * @return array{written: bool, path: string, content: string, values: array<string, string>, changed: bool}
     */
    public function ensureAppKey(): array
    {
        $path = app()->getRootPath() . '.env';
        $current = trim((string) env('APP_KEY', ''));
        if (KeyEncryptionService::isValidAppKey($current)) {
            return [
                'written' => true,
                'path' => $path,
                'content' => '',
                'values' => ['APP_KEY' => $current],
                'changed' => false,
            ];
        }

        $appKey = KeyEncryptionService::generateAppKey();
        if (is_file($path) && !is_readable($path)) {
            return [
                'written' => false,
                'path' => $path,
                'content' => 'APP_KEY = ' . $appKey . PHP_EOL,
                'values' => ['APP_KEY' => $appKey],
                'changed' => true,
            ];
        }

        $content = $this->mergeAppKeyIntoEnvFile($path, $appKey);
        $written = $this->writeTarget($path, $content);
        if ($written) {
            $this->applyRuntimeAppKey($appKey);
        }

        return [
            'written' => $written,
            'path' => $path,
            'content' => $content,
            'values' => ['APP_KEY' => $appKey],
            'changed' => true,
        ];
    }

    protected function writeTarget(string $path, string $content): bool
    {
        return @file_put_contents($path, $content) !== false;
    }

    /**
     * @param array<string, string> $values
     */
    protected function render(array $values): string
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = $key . ' = ' . $value;
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    protected function mergeAppKeyIntoEnvFile(string $path, string $appKey): string
    {
        $line = 'APP_KEY = ' . $appKey;
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        if ($content === '') {
            return $line . PHP_EOL;
        }

        if (preg_match('/^APP_KEY\s*=.*$/m', $content) === 1) {
            return (string) preg_replace('/^APP_KEY\s*=.*$/m', $line, $content, 1);
        }

        $separator = preg_match('/\R\z/', $content) === 1 ? '' : PHP_EOL;

        return $content . $separator . $line . PHP_EOL;
    }

    protected function applyRuntimeAppKey(string $appKey): void
    {
        $_ENV['APP_KEY'] = $appKey;
        putenv('PHP_APP_KEY=' . $appKey);
        EnvFacade::set('APP_KEY', $appKey);
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    protected function resolveValues(array $values): array
    {
        $defaults = [
            'APP_DEBUG' => 'false',
            'APP_KEY' => KeyEncryptionService::generateAppKey(),
            'COOKIE_SECURE' => 'true',
            'CACHE_DRIVER' => 'file',
            'CACHE_REDIS_HOST' => '127.0.0.1',
            'CACHE_REDIS_PORT' => '6379',
            'CACHE_REDIS_PASSWORD' => '',
            'CACHE_REDIS_SELECT' => '0',
            'CACHE_REDIS_TIMEOUT' => '0',
            'CACHE_REDIS_PREFIX' => 'vmq_',
            'CACHE_REDIS_PERSISTENT' => 'false',
            'SESSION_TYPE' => 'cache',
            'SESSION_STORE' => '',
            'DB_TYPE' => 'mysql',
            'DB_HOST' => '',
            'DB_NAME' => '',
            'DB_USER' => '',
            'DB_PASS' => '',
            'DB_PORT' => '3306',
            'DB_CHARSET' => 'utf8mb4',
            'DEFAULT_LANG' => 'zh-cn',
        ];

        $resolved = [];
        foreach (array_merge($defaults, $values) as $key => $value) {
            $resolved[$key] = (string) $value;
        }

        if (!KeyEncryptionService::isValidAppKey($resolved['APP_KEY'] ?? '')) {
            $resolved['APP_KEY'] = KeyEncryptionService::generateAppKey();
        }

        return $resolved;
    }
}
