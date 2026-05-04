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

    public function test_install_documentation_prioritizes_php_index_for_domain_root(): void
    {
        $readmePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'README-INSTALL.md';

        self::assertFileExists($readmePath);

        $readme = (string) file_get_contents($readmePath);

        self::assertStringContainsString('index index.php;', $readme);
        self::assertStringNotContainsString('index index.php index.html;', $readme);
        self::assertStringContainsString('public/index.html', $readme);
    }

    public function test_release_workflow_falls_back_to_app_version_for_manual_branch_runs(): void
    {
        $workflowPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github/workflows/release.yml';

        self::assertFileExists($workflowPath);

        $workflow = (string) file_get_contents($workflowPath);

        self::assertStringContainsString('INPUT_VERSION=', $workflow);
        self::assertStringContainsString('[[ "$VERSION" == *"/"* ]]', $workflow);
        self::assertStringContainsString('config/app.php', $workflow);
        self::assertStringNotContainsString('require "config/app.php"', $workflow);
        self::assertStringContainsString('preg_match("/[\x27\x22]ver', $workflow);
    }

    public function test_release_workflow_uploads_zip_and_sha256_assets(): void
    {
        $workflowPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github/workflows/release.yml';

        self::assertFileExists($workflowPath);

        $workflow = (string) file_get_contents($workflowPath);

        self::assertStringContainsString('sha256sum "${{ steps.version.outputs.package_name }}.zip"', $workflow);
        self::assertStringContainsString('${{ steps.version.outputs.package_name }}.zip.sha256', $workflow);
        self::assertStringContainsString('gh release upload "$VERSION" "$ZIP" "$SHA256" --clobber', $workflow);
    }

    public function test_release_workflow_stages_packages_outside_workspace(): void
    {
        $workflowPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github/workflows/release.yml';

        self::assertFileExists($workflowPath);

        $workflow = (string) file_get_contents($workflowPath);

        self::assertStringContainsString('${{ runner.temp }}/vpay-releases', $workflow);
        self::assertStringNotContainsString('--output=build/releases', $workflow);
    }

    public function test_release_output_directory_is_ignored_by_git(): void
    {
        $gitignorePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.gitignore';

        self::assertFileExists($gitignorePath);

        $gitignore = (string) file_get_contents($gitignorePath);

        self::assertStringContainsString('/build/releases/', $gitignore);
    }

    public function test_local_release_package_default_output_stays_outside_project_tree(): void
    {
        $scriptPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release-package.php';

        self::assertFileExists($scriptPath);

        $script = (string) file_get_contents($scriptPath);

        self::assertStringContainsString('sys_get_temp_dir()', $script);
        self::assertStringNotContainsString("'build' . DIRECTORY_SEPARATOR . 'releases'", $script);
    }

    public function test_build_directory_denies_web_access_under_apache(): void
    {
        $htaccessPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . '.htaccess';

        self::assertFileExists($htaccessPath);

        $htaccess = (string) file_get_contents($htaccessPath);

        self::assertStringContainsString('Require all denied', $htaccess);
    }
}
