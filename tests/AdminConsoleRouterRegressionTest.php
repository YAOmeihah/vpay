<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

final class AdminConsoleRouterRegressionTest extends TestCase
{
    public function test_fixed_home_tag_points_to_real_dashboard_route(): void
    {
        $layoutTypes = $this->readProjectFile('frontend/admin/src/layout/types.ts');
        $homeRoutes = $this->readProjectFile('frontend/admin/src/router/modules/home.ts');

        self::assertMatchesRegularExpression(
            '/path:\s*"\/dashboard"\s*,\s*name:\s*"DashboardHome"/s',
            $homeRoutes
        );
        self::assertMatchesRegularExpression(
            '/routerArrays[\s\S]*path:\s*"\/dashboard"[\s\S]*name:\s*"DashboardHome"[\s\S]*title:\s*"首页"/',
            $layoutTypes
        );
        self::assertStringNotContainsString('path: "/welcome"', $layoutTypes);
    }

    private function readProjectFile(string $relativePath): string
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $contents = file_get_contents($path);

        self::assertIsString($contents, $relativePath . ' should be readable');

        return $contents;
    }
}
