<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

final class RootPortalStaticPageTest extends TestCase
{
    private string $html;

    protected function setUp(): void
    {
        $this->html = (string) file_get_contents(dirname(__DIR__) . '/public/index.html');
    }

    public function test_root_page_is_now_a_portal_and_not_a_redirect_shell(): void
    {
        $this->assertStringNotContainsString('http-equiv="refresh"', $this->html);
        $this->assertStringNotContainsString('window.location.replace("/console/")', $this->html);
        $this->assertStringContainsString('支付处理与后台协同平台', $this->html);
        $this->assertStringNotContainsString('/console/', $this->html);
        $this->assertStringNotContainsString('/payment-api.html', $this->html);
        $this->assertStringNotContainsString('/createOrder', $this->html);
    }

    public function test_release_package_excludes_root_static_index_to_allow_install_bootstrap(): void
    {
        $builderPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'build/release/ReleasePackageBuilder.php';
        self::assertFileExists($builderPath);
        require_once $builderPath;

        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-root-static-release-' . bin2hex(random_bytes(6));
        $fixtureRoot = $base . DIRECTORY_SEPARATOR . 'root';
        $outputRoot = $base . DIRECTORY_SEPARATOR . 'out';

        try {
            $this->writeFixtureFile($fixtureRoot, 'app/AppService.php', '<?php');
            $this->writeFixtureFile($fixtureRoot, 'config/app.php', '<?php return [];');
            $this->writeFixtureFile($fixtureRoot, 'public/index.php', '<?php');
            $this->writeFixtureFile($fixtureRoot, 'public/index.html', '<h1>静态首页</h1>');
            $this->writeFixtureFile($fixtureRoot, 'public/console/index.html', '<div id="app"></div>');
            $this->writeFixtureFile($fixtureRoot, 'route/app.php', '<?php');
            $this->writeFixtureFile($fixtureRoot, 'vendor/autoload.php', '<?php');
            $this->writeFixtureFile($fixtureRoot, 'view/install/check.php', '<main></main>');

            $builder = new \VPay\Build\ReleasePackageBuilder($fixtureRoot);
            $packageDir = $builder->stage('v2.1.0', $outputRoot);

            self::assertFileExists($packageDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php');
            self::assertFileDoesNotExist($packageDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.html');
            self::assertFileExists($packageDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . 'index.html');
        } finally {
            $this->removeTree($base);
        }
    }

    public function test_apache_default_index_prefers_php_entrypoint_over_static_portal(): void
    {
        $htaccess = (string) file_get_contents(dirname(__DIR__) . '/public/.htaccess');

        self::assertStringContainsString('DirectoryIndex index.php index.html', $htaccess);
    }

    private function writeFixtureFile(string $root, string $relativePath, string $contents): void
    {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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
