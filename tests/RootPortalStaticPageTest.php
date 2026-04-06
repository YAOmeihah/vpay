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
}
