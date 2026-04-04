<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

final class RouteStructureRegressionTest extends TestCase
{
    public function test_route_list_keeps_public_entrypoints_and_points_to_split_controllers(): void
    {
        $output = shell_exec('php think route:list');

        $this->assertIsString($output);
        $this->assertRouteMapping($output, 'login', 'admin.Auth/login', '\*');
        $this->assertRouteMapping($output, 'getMenu', 'admin.Menu/getMenu', '\*');
        $this->assertRouteMapping($output, 'createOrder', 'merchant.Order/createOrder', '\*');
        $this->assertRouteMapping($output, 'getOrder', 'merchant.Order/getOrder', '\*');
        $this->assertRouteMapping($output, 'checkOrder', 'merchant.Order/checkOrder', '\*');
        $this->assertRouteMapping($output, 'closeOrder', 'merchant.Order/closeOrder', '\*');
        $this->assertRouteMapping($output, 'getState', 'monitor.Monitor/getState', '\*');
        $this->assertRouteMapping($output, 'appHeart', 'monitor.Monitor/appHeart', '\*');
        $this->assertRouteMapping($output, 'appPush', 'monitor.Monitor/appPush', '\*');
        $this->assertRouteMapping($output, 'closeEndOrder', 'monitor.Monitor/closeEndOrder', '\*');
        $this->assertRouteMapping($output, 'mapi.php', 'compat.Epay/mapi', 'post');
        $this->assertRouteMapping($output, 'submit.php', 'compat.Epay/submit', 'get\|post');
        $this->assertRouteMapping($output, 'api/pay/create', 'compat.Epay/createV2', 'post');
        $this->assertRouteMapping($output, 'api/pay/submit', 'compat.Epay/submitV2', 'post');
        $this->assertRouteMapping($output, 'admin/index/profile', 'admin/profile', '\*');
        $this->assertRouteMapping($output, 'admin/index/logout', 'admin/logout', 'post');
    }

    private function assertRouteMapping(string $output, string $rule, string $route, string $methodPattern): void
    {
        $pattern = '/\|\s+' . preg_quote($rule, '/') . '\s+\|\s+' . preg_quote($route, '/') . '\s+\|\s+' . $methodPattern . '\s+\|/';
        $this->assertMatchesRegularExpression($pattern, $output);
    }
}
