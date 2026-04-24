<?php
declare(strict_types=1);

use VPay\Build\ReleasePackageBuilder;

require __DIR__ . '/release/ReleasePackageBuilder.php';

$root = dirname(__DIR__);
$options = getopt('', ['version::', 'output::']);
$version = (string) ($options['version'] ?? getenv('GITHUB_REF_NAME') ?: detectAppVersion($root));
$output = (string) ($options['output'] ?? ($root . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'releases'));

$builder = new ReleasePackageBuilder($root);
$packageDir = $builder->stage($version, $output);

fwrite(STDOUT, $packageDir . PHP_EOL);

function detectAppVersion(string $root): string
{
    $configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
    if (is_file($configPath)) {
        $config = require $configPath;
        if (is_array($config) && isset($config['ver']) && trim((string) $config['ver']) !== '') {
            return (string) $config['ver'];
        }
    }

    return 'dev';
}
