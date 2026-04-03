<?php
declare(strict_types=1);

namespace tests;

final class TestEnvResolver
{
    /**
     * Resolve INI-style config by merging filesystem sources and falling back to env vars.
     *
     * @param string[] $files Ordered list of file paths (lowest precedence first).
     * @param array<string, string> $envVarMap Maps INI keys to VMQ_TEST_* environment variables.
     * @param callable(string): array|null $fileLoader Returns parsed key/value pairs or null.
     * @param callable(string): string|false $envGetter Returns env var value or false if unset.
     */
    public static function resolve(
        array $files,
        array $envVarMap,
        callable $fileLoader,
        callable $envGetter
    ): array {
        $env = [];

        foreach ($files as $file) {
            $parsed = $fileLoader($file);
            if (is_array($parsed)) {
                $env = array_merge($env, $parsed);
            }
        }

        foreach ($envVarMap as $iniKey => $envVar) {
            if (array_key_exists($iniKey, $env)) {
                continue;
            }

            $value = $envGetter($envVar);
            if ($value !== false) {
                $env[$iniKey] = $value;
            }
        }

        return $env;
    }
}
