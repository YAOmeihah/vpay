<?php
declare(strict_types=1);

namespace app\service\install;

class EnvWriter
{
    /**
     * @param array<string, string> $values
     * @return array{written: bool, path: string, content: string}
     */
    public function write(array $values): array
    {
        $content = $this->render($values);
        $path = app()->getRootPath() . '.env';

        return [
            'written' => $this->writeTarget($path, $content),
            'path' => $path,
            'content' => $content,
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
            $lines[] = $key . ' = ' . $value;
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
