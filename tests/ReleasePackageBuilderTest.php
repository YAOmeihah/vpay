<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleasePackageBuilderTest extends TestCase
{
    private string $fixtureRoot;
    private string $outputRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-release-package-' . bin2hex(random_bytes(6));
        $this->fixtureRoot = $base . DIRECTORY_SEPARATOR . 'root';
        $this->outputRoot = $base . DIRECTORY_SEPARATOR . 'out';
        @mkdir($this->fixtureRoot, 0777, true);
        @mkdir($this->outputRoot, 0777, true);

        $this->writeFixtureFile('app/AppService.php', '<?php');
        $this->writeFixtureFile('config/app.php', "<?php return ['ver' => '9.8.7'];");
        $this->writeFixtureFile('database/migrations/2.1.0/001.sql', 'SELECT 1;');
        $this->writeFixtureFile('public/index.php', '<?php');
        $this->writeFixtureFile('public/index.html', '<h1>静态首页</h1>');
        $this->writeFixtureFile('public/.htaccess', 'RewriteEngine On');
        $this->writeFixtureFile('public/console/index.html', '<div id="app"></div>');
        $this->writeFixtureFile('public/console/static/js/index.js', 'console.log("ok");');
        $this->writeFixtureFile('public/runtime/cache.tmp', 'runtime cache');
        $this->writeFixtureFile('route/app.php', '<?php');
        $this->writeFixtureFile('vendor/autoload.php', '<?php');
        $this->writeFixtureFile('vendor/package/file.php', '<?php');
        $this->writeFixtureFile('view/install/check.php', '<main></main>');
        $this->writeFixtureFile('composer.json', '{}');
        $this->writeFixtureFile('composer.lock', '{}');
        $this->writeFixtureFile('.example.env', 'DB_NAME = test');
        $this->writeFixtureFile('.env', 'DB_PASS = secret');
        $this->writeFixtureFile('README-INSTALL.md', '# Install');
        $this->writeFixtureFile('think', '#!/usr/bin/env php');
        $this->writeFixtureFile('vmq.sql', 'CREATE TABLE setting(id int);');
        $this->writeFixtureFile('tests/ExampleTest.php', '<?php');
        $this->writeFixtureFile('frontend/admin/src/main.ts', 'source');
        $this->writeFixtureFile('frontend/admin/node_modules/pkg/index.js', 'dependency');
        $this->writeFixtureFile('.git/config', 'repo');
    }

    protected function tearDown(): void
    {
        $this->removeTree(dirname($this->fixtureRoot));

        parent::tearDown();
    }

    public function test_stage_creates_clean_installable_release_tree(): void
    {
        $builderPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'build/release/ReleasePackageBuilder.php';
        self::assertFileExists($builderPath);
        require_once $builderPath;

        $builder = new \VPay\Build\ReleasePackageBuilder($this->fixtureRoot);
        $packageDir = $builder->stage('v2.1.0', $this->outputRoot);

        self::assertSame('vpay-v2.1.0', basename($packageDir));
        self::assertFileExists($packageDir . DIRECTORY_SEPARATOR . 'vendor/autoload.php');
        self::assertFileExists($packageDir . DIRECTORY_SEPARATOR . 'public/index.html');
        self::assertFileExists($packageDir . DIRECTORY_SEPARATOR . 'public/console/index.html');
        self::assertFileExists($packageDir . DIRECTORY_SEPARATOR . '.example.env');
        self::assertFileExists($packageDir . DIRECTORY_SEPARATOR . 'README-INSTALL.md');
        self::assertDirectoryExists($packageDir . DIRECTORY_SEPARATOR . 'extend');
        self::assertFileExists($packageDir . DIRECTORY_SEPARATOR . 'runtime/install/.keep');

        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'release-manifest.json';
        self::assertFileExists($manifestPath);
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        self::assertIsArray($manifest);
        self::assertSame('v2.1.0', $manifest['version'] ?? null);
        self::assertSame('9.8.7', $manifest['app_version'] ?? null);

        self::assertFileDoesNotExist($packageDir . DIRECTORY_SEPARATOR . '.env');
        self::assertFileDoesNotExist($packageDir . DIRECTORY_SEPARATOR . 'tests/ExampleTest.php');
        self::assertFileDoesNotExist($packageDir . DIRECTORY_SEPARATOR . 'frontend/admin/src/main.ts');
        self::assertFileDoesNotExist($packageDir . DIRECTORY_SEPARATOR . 'frontend/admin/node_modules/pkg/index.js');
        self::assertFileDoesNotExist($packageDir . DIRECTORY_SEPARATOR . 'public/runtime/cache.tmp');
        self::assertFileDoesNotExist($packageDir . DIRECTORY_SEPARATOR . 'runtime/install/enable.flag');
        self::assertFileDoesNotExist($packageDir . DIRECTORY_SEPARATOR . '.git/config');
    }

    public function test_stage_requires_production_dependencies_and_console_build(): void
    {
        $builderPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'build/release/ReleasePackageBuilder.php';
        self::assertFileExists($builderPath);
        require_once $builderPath;

        $this->removeTree($this->fixtureRoot . DIRECTORY_SEPARATOR . 'vendor');
        $builder = new \VPay\Build\ReleasePackageBuilder($this->fixtureRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('vendor/autoload.php');

        $builder->stage('v2.1.0', $this->outputRoot);
    }

    public function test_stage_reads_app_version_without_bootstrapping_thinkphp(): void
    {
        $builderPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'build/release/ReleasePackageBuilder.php';
        self::assertFileExists($builderPath);
        require_once $builderPath;

        $this->writeFixtureFile(
            'config/app.php',
            "<?php return ['exception_tmpl' => release_builder_should_not_execute_config(), 'ver' => '9.8.8'];"
        );

        $builder = new \VPay\Build\ReleasePackageBuilder($this->fixtureRoot);
        $packageDir = $builder->stage('v2.1.0', $this->outputRoot);
        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'release-manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        self::assertIsArray($manifest);
        self::assertSame('9.8.8', $manifest['app_version'] ?? null);
    }

    private function writeFixtureFile(string $relativePath, string $contents): void
    {
        $path = $this->fixtureRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $contents);
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
