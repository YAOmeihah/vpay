<?php
declare(strict_types=1);

namespace tests;

use app\service\update\UpdateReleaseService;
use PHPUnit\Framework\TestCase;

final class UpdateReleaseServiceTest extends TestCase
{
    public function test_reports_update_available_for_newer_stable_release(): void
    {
        $service = new UpdateReleaseService('2.1.1');

        $result = $service->checkFromRelease($this->release('v2.1.2'));

        self::assertSame('update_available', $result['status']);
        self::assertSame('2.1.1', $result['current_version']);
        self::assertSame('2.1.2', $result['latest_version']);
        self::assertSame('v2.1.2', $result['tag_name']);
        self::assertSame('https://example.test/vpay-v2.1.2.zip', $result['assets']['zip']['download_url']);
        self::assertSame('https://example.test/vpay-v2.1.2.zip.sha256', $result['assets']['sha256']['download_url']);
    }

    public function test_ignores_prerelease_and_draft_releases(): void
    {
        $service = new UpdateReleaseService('2.1.1');

        self::assertSame('check_failed', $service->checkFromRelease($this->release('v2.1.2', prerelease: true))['status']);
        self::assertSame('check_failed', $service->checkFromRelease($this->release('v2.1.2', draft: true))['status']);
    }

    public function test_reports_up_to_date_and_ahead_states(): void
    {
        $service = new UpdateReleaseService('2.1.1');

        self::assertSame('up_to_date', $service->checkFromRelease($this->release('v2.1.1'))['status']);
        self::assertSame('ahead', $service->checkFromRelease($this->release('v2.1.0'))['status']);
    }

    public function test_requires_zip_and_sha256_assets(): void
    {
        $service = new UpdateReleaseService('2.1.1');
        $release = $this->release('v2.1.2');
        $release['assets'] = [
            ['name' => 'vpay-v2.1.2.zip', 'browser_download_url' => 'https://example.test/vpay-v2.1.2.zip', 'size' => 123],
        ];

        $result = $service->checkFromRelease($release);

        self::assertSame('check_failed', $result['status']);
        self::assertStringContainsString('sha256', $result['message']);
    }

    private function release(string $tag, bool $prerelease = false, bool $draft = false): array
    {
        return [
            'tag_name' => $tag,
            'name' => $tag,
            'html_url' => 'https://github.com/YAOmeihah/vpay/releases/tag/' . $tag,
            'published_at' => '2026-04-25T00:00:00Z',
            'body' => 'Release notes',
            'draft' => $draft,
            'prerelease' => $prerelease,
            'assets' => [
                ['name' => "vpay-{$tag}.zip", 'browser_download_url' => "https://example.test/vpay-{$tag}.zip", 'size' => 123],
                ['name' => "vpay-{$tag}.zip.sha256", 'browser_download_url' => "https://example.test/vpay-{$tag}.zip.sha256", 'size' => 90],
            ],
        ];
    }
}
