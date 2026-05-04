<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

$_ENV['APP_KEY'] = $_ENV['APP_KEY'] ?? '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
putenv('PHP_APP_KEY=' . $_ENV['APP_KEY']);

require __DIR__ . '/TestEnvResolver.php';
require __DIR__ . '/TestCase.php';
