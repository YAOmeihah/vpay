<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase
{
    public function test_release_workflow_builds_full_installable_package(): void
    {
        $workflowPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github/workflows/release.yml';

        self::assertFileExists($workflowPath);

        $workflow = (string) file_get_contents($workflowPath);

        self::assertStringContainsString('pnpm/action-setup', $workflow);
        self::assertStringContainsString('composer install --no-dev', $workflow);
        self::assertStringContainsString('pnpm install --frozen-lockfile', $workflow);
        self::assertStringContainsString('pnpm typecheck', $workflow);
        self::assertStringContainsString('pnpm build', $workflow);
        self::assertStringContainsString('php build/release-package.php', $workflow);
        self::assertStringContainsString('actions/upload-artifact', $workflow);
        self::assertStringContainsString('gh release', $workflow);
    }

    public function test_release_workflow_falls_back_to_app_version_for_manual_branch_runs(): void
    {
        $workflowPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github/workflows/release.yml';

        self::assertFileExists($workflowPath);

        $workflow = (string) file_get_contents($workflowPath);

        self::assertStringContainsString('INPUT_VERSION=', $workflow);
        self::assertStringContainsString('[[ "$VERSION" == *"/"* ]]', $workflow);
        self::assertStringContainsString('config/app.php', $workflow);
    }

    public function test_release_output_directory_is_ignored_by_git(): void
    {
        $gitignorePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.gitignore';

        self::assertFileExists($gitignorePath);

        $gitignore = (string) file_get_contents($gitignorePath);

        self::assertStringContainsString('/build/releases/', $gitignore);
    }
}
