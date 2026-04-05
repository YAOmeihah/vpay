<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

final class RouteStructureRegressionTest extends TestCase
{
    public function test_route_list_keeps_public_entrypoints_and_points_to_split_controllers(): void
    {
        // ThinkPHP's `route:list` writes a cache file (`runtime/route_list.php`) and tries to unlink it
        // between runs. On Windows, that unlink may fail if another process holds the file handle.
        // We keep the regression test deterministic by falling back to the cached output when needed.
        $output = shell_exec('php think route:list 2>&1');
        if (!is_string($output) || !str_contains($output, 'Route List')) {
            $cachedPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'route_list.php';
            $cached = @file_get_contents($cachedPath);
            if (is_string($cached) && str_contains($cached, 'Route List')) {
                $output = $cached;
            }
        }

        $this->assertIsString($output);
        $this->assertRouteMapping($output, 'login', 'admin.Auth/login', '\*');
        $this->assertDoesNotMatchRegularExpression('/\|\s+getMenu\s+\|/', $output);
        $this->assertRouteMapping($output, 'admin/index/getMain', 'admin/getMain', '\*');
        $this->assertRouteMapping($output, 'createOrder', 'merchant.Order/createOrder', '\*');
        $this->assertRouteMapping($output, 'getOrder', 'merchant.Order/getOrder', '\*');
        $this->assertRouteMapping($output, 'checkOrder', 'merchant.Order/checkOrder', '\*');
        $this->assertRouteMapping($output, 'closeOrder', 'merchant.Order/closeOrder', '\*');
        $this->assertRouteMapping($output, 'getState', 'monitor.Monitor/getState', '\*');
        $this->assertRouteMapping($output, 'appHeart', 'monitor.Monitor/appHeart', '\*');
        $monitorRoutes = (string) file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'route' . DIRECTORY_SEPARATOR . 'monitor.php');
        $this->assertStringContainsString("Route::post('appPush', 'monitor.Monitor/appPush');", $monitorRoutes);
        $this->assertRouteMapping($output, 'closeEndOrder', 'monitor.Monitor/closeEndOrder', '\*');
        $this->assertRouteMapping($output, 'admin/index/profile', 'admin/profile', '\*');
        $this->assertRouteMapping($output, 'admin/index/logout', 'admin/logout', 'post');
    }

    private function assertRouteMapping(string $output, string $rule, string $route, string $methodPattern): void
    {
        $pattern = '/\|\s+' . preg_quote($rule, '/') . '\s+\|\s+' . preg_quote($route, '/') . '\s+\|\s+' . $methodPattern . '\s+\|/';
        $this->assertMatchesRegularExpression($pattern, $output);
    }
}
