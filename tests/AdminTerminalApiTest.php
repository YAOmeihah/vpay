<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminTerminalApiTest extends TestCase
{
    public function test_get_terminals_route_is_registered(): void
    {
        $routes = file_get_contents(__DIR__ . '/../route/admin.php');

        self::assertIsString($routes);
        self::assertStringContainsString("Route::any('getTerminals'", $routes);
        self::assertStringContainsString("Route::post('saveTerminal'", $routes);
        self::assertStringContainsString("Route::any('getTerminalChannels'", $routes);
    }
}
