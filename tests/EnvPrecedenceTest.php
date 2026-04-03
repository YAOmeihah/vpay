<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

class EnvPrecedenceTest extends BaseTestCase
{
    public function test_env_testing_overrides_env_and_env_vars_fill_missing_values(): void
    {
        $files = [
            '/project/.env',
            '/project/.env.testing',
        ];

        $map = [
            'DB_TYPE' => 'VMQ_TEST_DB_TYPE',
            'DB_HOST' => 'VMQ_TEST_DB_HOST',
            'DB_PORT' => 'VMQ_TEST_DB_PORT',
            'DB_NAME' => 'VMQ_TEST_DB_NAME',
            'DB_USER' => 'VMQ_TEST_DB_USER',
            'DB_PASS' => 'VMQ_TEST_DB_PASS',
            'DB_CHARSET' => 'VMQ_TEST_DB_CHARSET',
        ];

        $fileLoader = static function (string $file) {
            if (str_contains($file, '.env.testing')) {
                return [
                    'DB_HOST' => 'testing-host',
                    'DB_NAME' => 'testing-db',
                ];
            }

            if (str_contains($file, '.env')) {
                return [
                    'DB_TYPE' => 'mysql',
                    'DB_HOST' => 'base-host',
                    'DB_USER' => 'base-user',
                ];
            }

            return null;
        };

        $envGetter = static function (string $envVar) {
            return match ($envVar) {
                'VMQ_TEST_DB_PORT' => '3307',
                'VMQ_TEST_DB_PASS' => 'secret',
                'VMQ_TEST_DB_CHARSET' => 'utf8mb4',
                'VMQ_TEST_DB_USER' => 'env-user',
                'VMQ_TEST_DB_HOST' => 'env-host',
                default => false,
            };
        };

        $result = TestEnvResolver::resolve($files, $map, $fileLoader, $envGetter);

        $this->assertSame([
            'DB_TYPE' => 'mysql',
            'DB_HOST' => 'testing-host',
            'DB_USER' => 'base-user',
            'DB_NAME' => 'testing-db',
            'DB_PORT' => '3307',
            'DB_PASS' => 'secret',
            'DB_CHARSET' => 'utf8mb4',
        ], $result);
    }
}
