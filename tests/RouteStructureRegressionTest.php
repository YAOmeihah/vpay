<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

final class RouteStructureRegressionTest extends TestCase
{
    public function test_route_list_keeps_public_entrypoints_and_points_to_split_controllers(): void
    {
        $routes = file_get_contents(dirname(__DIR__) . '/route/app.php');
        $routes .= "\n" . file_get_contents(dirname(__DIR__) . '/route/admin.php');
        $routes .= "\n" . file_get_contents(dirname(__DIR__) . '/route/merchant.php');
        $routes .= "\n" . file_get_contents(dirname(__DIR__) . '/route/monitor.php');
        $routes .= "\n" . file_get_contents(dirname(__DIR__) . '/route/compat.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("Route::any('login', 'admin.Auth/login');", $routes);
        $this->assertStringContainsString("Route::any('getMenu', 'admin.Menu/getMenu')", $routes);
        $this->assertStringContainsString("Route::any('createOrder', 'merchant.Order/createOrder');", $routes);
        $this->assertStringContainsString("Route::any('getOrder', 'merchant.Order/getOrder');", $routes);
        $this->assertStringContainsString("Route::any('checkOrder', 'merchant.Order/checkOrder');", $routes);
        $this->assertStringContainsString("Route::any('closeOrder', 'merchant.Order/closeOrder');", $routes);
        $this->assertStringContainsString("Route::any('getState', 'monitor.Monitor/getState');", $routes);
        $this->assertStringContainsString("Route::any('appHeart', 'monitor.Monitor/appHeart');", $routes);
        $this->assertStringContainsString("Route::any('appPush', 'monitor.Monitor/appPush');", $routes);
        $this->assertStringContainsString("Route::any('closeEndOrder', 'monitor.Monitor/closeEndOrder');", $routes);
        $this->assertStringContainsString("Route::post('mapi.php', 'compat.Epay/mapi');", $routes);
        $this->assertStringContainsString("Route::rule('submit.php', 'compat.Epay/submit', 'GET|POST');", $routes);
        $this->assertStringContainsString("Route::post('api/pay/create', 'compat.Epay/createV2');", $routes);
        $this->assertStringContainsString("Route::post('api/pay/submit', 'compat.Epay/submitV2');", $routes);
        $this->assertStringContainsString("Route::any('profile', 'admin/profile');", $routes);
        $this->assertStringContainsString("Route::post('logout', 'admin/logout');", $routes);
    }
}
