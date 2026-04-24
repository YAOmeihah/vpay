# Admin Auto Update Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a backend-admin-confirmed auto-update flow that checks GitHub Releases, downloads a verified package, backs up current files, applies the update, and reuses the existing migration runner.

**Architecture:** The update system is a thin orchestration layer around the existing installer upgrade infrastructure. New `app/service/update/*` services own GitHub release detection, preflight checks, package verification, backup, state files, and file application; database upgrades still go through `app/service/install/MigrationRunner.php`. The Vue admin settings page gets a `SystemUpdateCard` that calls protected backend endpoints.

**Tech Stack:** ThinkPHP 8, PHP 8.2-compatible services, PHPUnit 11, GitHub REST Releases API, `ZipArchive`, Vue 3, TypeScript, Element Plus, existing release workflow.

---

## File Structure

- Modify `.github/workflows/release.yml` to generate and upload `vpay-vX.Y.Z.zip.sha256`.
- Modify `build/release/ReleasePackageBuilder.php` to include `app_version` in `release-manifest.json`.
- Create `app/service/update/GitHubReleaseClient.php` for GitHub API transport.
- Create `app/service/update/UpdateReleaseService.php` for release comparison and asset selection.
- Create `app/service/update/UpdateStateStore.php` for update lock/status/success/error files.
- Create `app/service/update/UpdatePreflightService.php` for environment checks.
- Create `app/service/update/UpdatePackageService.php` for download, checksum, extraction, and package validation.
- Create `app/service/update/UpdateBackupService.php` for file backup.
- Create `app/service/update/UpdateApplyService.php` for file replacement and migration orchestration.
- Create `app/controller/admin/Update.php` for admin update APIs.
- Modify `route/admin.php` to route update endpoints to the new controller.
- Create `frontend/admin/src/api/admin/update.ts` for frontend API calls.
- Create `frontend/admin/src/views/system/settings/updateState.ts` for pure UI state helpers.
- Create `frontend/admin/src/views/system/settings/components/SystemUpdateCard.vue`.
- Modify `frontend/admin/src/views/system/settings/index.vue` to mount the update card.
- Add PHPUnit tests under `tests/*Update*Test.php`.
- Add frontend tests under `frontend/admin/tests/systemUpdateState.test.ts`.

---

### Task 1: Release Manifest And SHA256 Assets

**Files:**
- Modify: `.github/workflows/release.yml`
- Modify: `build/release/ReleasePackageBuilder.php`
- Modify: `tests/ReleasePackageBuilderTest.php`
- Modify: `tests/ReleaseWorkflowTest.php`

- [ ] **Step 1: Write failing release package tests**

Update `tests/ReleasePackageBuilderTest.php` so the fixture app version is explicit and the manifest is parsed.

```php
$this->writeFixtureFile('config/app.php', "<?php return ['ver' => '9.8.7'];");
```

Add these assertions inside `test_stage_creates_clean_installable_release_tree()` after `$packageDir` is created:

```php
$manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'release-manifest.json';
self::assertFileExists($manifestPath);
$manifest = json_decode((string) file_get_contents($manifestPath), true);
self::assertIsArray($manifest);
self::assertSame('v2.1.0', $manifest['version'] ?? null);
self::assertSame('9.8.7', $manifest['app_version'] ?? null);
```

Update `tests/ReleaseWorkflowTest.php` with a new test:

```php
public function test_release_workflow_uploads_zip_and_sha256_assets(): void
{
    $workflowPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github/workflows/release.yml';

    self::assertFileExists($workflowPath);

    $workflow = (string) file_get_contents($workflowPath);

    self::assertStringContainsString('sha256sum "${{ steps.version.outputs.package_name }}.zip"', $workflow);
    self::assertStringContainsString('${{ steps.version.outputs.package_name }}.zip.sha256', $workflow);
    self::assertStringContainsString('gh release upload "$VERSION" "$ZIP" "$SHA256" --clobber', $workflow);
}
```

- [ ] **Step 2: Run tests and verify RED**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\ReleasePackageBuilderTest.php tests\ReleaseWorkflowTest.php
```

Expected: failures for missing `app_version` and missing `.sha256` workflow upload.

- [ ] **Step 3: Implement release metadata**

Modify `build/release/ReleasePackageBuilder.php`.

Add this before manifest writing:

```php
$appVersion = $this->appVersion($root);
```

Change manifest JSON to include:

```php
'app_version' => $appVersion,
```

Add a helper method:

```php
private function appVersion(string $root): string
{
    $configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
    if (!is_file($configPath)) {
        return '';
    }

    $config = require $configPath;
    if (!is_array($config)) {
        return '';
    }

    return (string) ($config['ver'] ?? '');
}
```

- [ ] **Step 4: Implement workflow checksum asset**

Modify `.github/workflows/release.yml`.

In `Archive release package`, append:

```bash
sha256sum "${{ steps.version.outputs.package_name }}.zip" > "${{ steps.version.outputs.package_name }}.zip.sha256"
```

In `Upload release artifact`, change `path` to a multi-line value:

```yaml
path: |
  build/releases/${{ steps.version.outputs.package_name }}.zip
  build/releases/${{ steps.version.outputs.package_name }}.zip.sha256
```

In `Publish GitHub Release asset`, add:

```bash
SHA256="build/releases/${{ steps.version.outputs.package_name }}.zip.sha256"
```

Change both upload/create commands so the release contains both assets:

```bash
gh release upload "$VERSION" "$ZIP" "$SHA256" --clobber
gh release create "$VERSION" "$ZIP" "$SHA256" --title "$VERSION" --notes "Full installable VPay package for $VERSION."
```

- [ ] **Step 5: Verify GREEN**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\ReleasePackageBuilderTest.php tests\ReleaseWorkflowTest.php
```

Expected: `OK`.

- [ ] **Step 6: Commit**

```powershell
git add .github/workflows/release.yml build/release/ReleasePackageBuilder.php tests/ReleasePackageBuilderTest.php tests/ReleaseWorkflowTest.php
git commit -m "build: publish release checksums"
```

---

### Task 2: GitHub Release Detection Service

**Files:**
- Create: `app/service/update/GitHubReleaseClient.php`
- Create: `app/service/update/UpdateReleaseService.php`
- Create: `tests/UpdateReleaseServiceTest.php`

- [ ] **Step 1: Write failing release service tests**

Create `tests/UpdateReleaseServiceTest.php`:

```php
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
```

- [ ] **Step 2: Run test and verify RED**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdateReleaseServiceTest.php
```

Expected: class not found for `UpdateReleaseService`.

- [ ] **Step 3: Implement GitHub client**

Create `app/service/update/GitHubReleaseClient.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\update;

use RuntimeException;

final class GitHubReleaseClient
{
    public function __construct(
        private readonly string $repository = 'YAOmeihah/vpay',
        private readonly int $timeoutSeconds = 10,
        private readonly string $token = ''
    ) {
    }

    public function latest(): array
    {
        $url = 'https://api.github.com/repos/' . $this->repository . '/releases/latest';
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: VPay-Updater',
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $body = $this->request($url, $headers);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('GitHub Release 响应不是有效 JSON');
        }

        return $decoded;
    }

    private function request(string $url, array $headers): string
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if (!is_string($body) || $body === '' || $status >= 400) {
                throw new RuntimeException('GitHub Release 请求失败: ' . ($error !== '' ? $error : 'HTTP ' . $status));
            }

            return $body;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeoutSeconds,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (!is_string($body) || $body === '') {
            throw new RuntimeException('GitHub Release 请求失败');
        }

        return $body;
    }
}
```

- [ ] **Step 4: Implement release comparison service**

Create `app/service/update/UpdateReleaseService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\update;

final class UpdateReleaseService
{
    public function __construct(
        private readonly string $currentVersion = '',
        private readonly ?GitHubReleaseClient $client = null
    ) {
    }

    public function check(): array
    {
        try {
            return $this->checkFromRelease($this->client()->latest());
        } catch (\Throwable $exception) {
            return [
                'status' => 'check_failed',
                'message' => $exception->getMessage(),
                'current_version' => $this->currentVersion(),
            ];
        }
    }

    public function checkFromRelease(array $release): array
    {
        $current = $this->currentVersion();
        $tag = (string) ($release['tag_name'] ?? '');
        $latest = ltrim($tag, 'vV');

        if (($release['draft'] ?? false) === true || ($release['prerelease'] ?? false) === true) {
            return $this->failed('最新 Release 不是正式版本', $current, $tag);
        }

        if (!preg_match('/^v\d+\.\d+\.\d+$/', $tag)) {
            return $this->failed('Release tag 格式不正确', $current, $tag);
        }

        $zipName = 'vpay-' . $tag . '.zip';
        $shaName = $zipName . '.sha256';
        $zip = $this->assetByName((array) ($release['assets'] ?? []), $zipName);
        $sha = $this->assetByName((array) ($release['assets'] ?? []), $shaName);
        if ($zip === null) {
            return $this->failed('Release 缺少安装包: ' . $zipName, $current, $tag);
        }
        if ($sha === null) {
            return $this->failed('Release 缺少 sha256 校验文件: ' . $shaName, $current, $tag);
        }

        $status = 'update_available';
        if (version_compare($current, $latest, '=')) {
            $status = 'up_to_date';
        } elseif (version_compare($current, $latest, '>')) {
            $status = 'ahead';
        }

        return [
            'status' => $status,
            'message' => $status === 'update_available' ? '发现新版本' : ($status === 'up_to_date' ? '程序是最新版' : '当前版本高于远程版本'),
            'current_version' => $current,
            'latest_version' => $latest,
            'tag_name' => $tag,
            'release_url' => (string) ($release['html_url'] ?? ''),
            'published_at' => (string) ($release['published_at'] ?? ''),
            'body' => (string) ($release['body'] ?? ''),
            'assets' => [
                'zip' => $this->assetPayload($zip),
                'sha256' => $this->assetPayload($sha),
            ],
        ];
    }

    private function currentVersion(): string
    {
        return $this->currentVersion !== '' ? ltrim($this->currentVersion, 'vV') : (string) config('app.ver', '');
    }

    private function client(): GitHubReleaseClient
    {
        return $this->client ?? new GitHubReleaseClient();
    }

    private function assetByName(array $assets, string $name): ?array
    {
        foreach ($assets as $asset) {
            if (is_array($asset) && (string) ($asset['name'] ?? '') === $name) {
                return $asset;
            }
        }

        return null;
    }

    private function assetPayload(array $asset): array
    {
        return [
            'name' => (string) ($asset['name'] ?? ''),
            'download_url' => (string) ($asset['browser_download_url'] ?? ''),
            'size' => (int) ($asset['size'] ?? 0),
        ];
    }

    private function failed(string $message, string $current, string $tag): array
    {
        return [
            'status' => 'check_failed',
            'message' => $message,
            'current_version' => $current,
            'tag_name' => $tag,
        ];
    }
}
```

- [ ] **Step 5: Verify GREEN**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdateReleaseServiceTest.php
```

Expected: `OK`.

- [ ] **Step 6: Commit**

```powershell
git add app/service/update/GitHubReleaseClient.php app/service/update/UpdateReleaseService.php tests/UpdateReleaseServiceTest.php
git commit -m "feat: detect github release updates"
```

---

### Task 3: Update State And Preflight Checks

**Files:**
- Create: `app/service/update/UpdateStateStore.php`
- Create: `app/service/update/UpdatePreflightService.php`
- Create: `tests/UpdatePreflightServiceTest.php`

- [ ] **Step 1: Write failing preflight tests**

Create `tests/UpdatePreflightServiceTest.php`:

```php
<?php
declare(strict_types=1);

namespace tests;

use app\service\update\UpdatePreflightService;
use app\service\update\UpdateStateStore;
use PHPUnit\Framework\TestCase;

final class UpdatePreflightServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpay-update-preflight-' . bin2hex(random_bytes(4));
        foreach (['app', 'config', 'database', 'route', 'vendor', 'view', 'public', 'runtime/install'] as $dir) {
            mkdir($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir), 0777, true);
        }
        file_put_contents($this->root . DIRECTORY_SEPARATOR . '.env', 'APP_DEBUG=false');
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        parent::tearDown();
    }

    public function test_preflight_passes_when_required_paths_are_writable_and_no_locks_exist(): void
    {
        $service = new UpdatePreflightService($this->root, new UpdateStateStore($this->root));

        $result = $service->check(['zip_size' => 1024]);

        self::assertTrue($result['ok']);
        self::assertSame([], array_values(array_filter($result['checks'], static fn (array $check): bool => $check['ok'] !== true)));
    }

    public function test_preflight_fails_when_update_lock_exists(): void
    {
        $store = new UpdateStateStore($this->root);
        $store->writeLock(['stage' => 'apply']);

        $result = (new UpdatePreflightService($this->root, $store))->check(['zip_size' => 1024]);

        self::assertFalse($result['ok']);
        self::assertContains('当前已有更新任务正在执行', array_column($result['checks'], 'message'));
    }

    public function test_state_store_writes_and_reads_last_error(): void
    {
        $store = new UpdateStateStore($this->root);
        $store->writeError(['stage' => 'download', 'message' => '网络失败']);

        self::assertSame('download', $store->lastError()['stage']);
        self::assertSame('网络失败', $store->lastError()['message']);
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
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                $this->removeTree($path . DIRECTORY_SEPARATOR . $item);
            }
        }
        @rmdir($path);
    }
}
```

- [ ] **Step 2: Run test and verify RED**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdatePreflightServiceTest.php
```

Expected: class not found for `UpdatePreflightService`.

- [ ] **Step 3: Implement update state store**

Create `app/service/update/UpdateStateStore.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\update;

final class UpdateStateStore
{
    public function __construct(private readonly ?string $rootPath = null)
    {
    }

    public function updatePath(): string
    {
        return $this->root() . 'runtime' . DIRECTORY_SEPARATOR . 'update';
    }

    public function lockPath(): string
    {
        return $this->updatePath() . DIRECTORY_SEPARATOR . 'update.lock';
    }

    public function statusPath(): string
    {
        return $this->updatePath() . DIRECTORY_SEPARATOR . 'status.json';
    }

    public function lastErrorPath(): string
    {
        return $this->updatePath() . DIRECTORY_SEPARATOR . 'last-error.json';
    }

    public function hasLock(): bool
    {
        return is_file($this->lockPath());
    }

    public function writeLock(array $payload): void
    {
        $this->ensureUpdatePath();
        file_put_contents($this->lockPath(), $this->encode($payload + ['updated_at' => time()]));
    }

    public function clearLock(): void
    {
        if (is_file($this->lockPath())) {
            @unlink($this->lockPath());
        }
    }

    public function writeStatus(array $payload): void
    {
        $this->ensureUpdatePath();
        file_put_contents($this->statusPath(), $this->encode($payload + ['updated_at' => time()]));
    }

    public function status(): array
    {
        return $this->readJson($this->statusPath());
    }

    public function writeError(array $payload): void
    {
        $this->ensureUpdatePath();
        file_put_contents($this->lastErrorPath(), $this->encode($payload + ['created_at' => time()]));
    }

    public function lastError(): array
    {
        return $this->readJson($this->lastErrorPath());
    }

    public function ensureUpdatePath(): void
    {
        $path = $this->updatePath();
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function root(): string
    {
        $root = $this->rootPath ?? app()->getRootPath();
        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }
}
```

- [ ] **Step 4: Implement preflight service**

Create `app/service/update/UpdatePreflightService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service\update;

final class UpdatePreflightService
{
    public function __construct(
        private readonly ?string $rootPath = null,
        private readonly ?UpdateStateStore $stateStore = null
    ) {
    }

    public function check(array $release = []): array
    {
        $checks = [];
        $root = $this->root();
        $store = $this->stateStore();

        $checks[] = $this->checkBool('更新目录可写', $this->ensureWritableDirectory($store->updatePath()), 'runtime/update/ 可创建并写入');
        $checks[] = $this->checkBool('项目根目录可写', is_writable($root), '项目根目录可写');

        foreach (['app', 'config', 'database', 'route', 'vendor', 'view', 'public'] as $dir) {
            $path = $root . $dir;
            $checks[] = $this->checkBool($dir . ' 可写', is_dir($path) && is_writable($path), $dir . ' 目录可写');
        }

        $checks[] = $this->checkBool('.env 可读', is_readable($root . '.env'), '.env 可读且不会被覆盖');
        $checks[] = $this->checkBool('ZipArchive 可用', class_exists(\ZipArchive::class), 'PHP ZipArchive 扩展可用');
        $checks[] = $this->checkBool('HTTPS 下载能力可用', function_exists('curl_init') || ini_get('allow_url_fopen'), 'curl 或 allow_url_fopen 可用');
        $checks[] = $this->checkBool('没有更新锁', !$store->hasLock(), '当前已有更新任务正在执行');
        $checks[] = $this->checkBool('没有安装锁', !is_file($root . 'runtime' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'lock.json'), '安装或升级正在执行');
        $checks[] = $this->checkBool('没有安装恢复错误', !is_file($root . 'runtime' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'last-error.json'), '安装或升级失败状态需要先处理');

        $zipSize = (int) ($release['zip_size'] ?? 0);
        $free = @disk_free_space($root);
        $checks[] = $this->checkBool('磁盘空间充足', $free === false || $zipSize <= 0 || $free >= ($zipSize * 3), '磁盘剩余空间不足');

        return [
            'ok' => count(array_filter($checks, static fn (array $check): bool => $check['ok'] !== true)) === 0,
            'checks' => $checks,
        ];
    }

    private function checkBool(string $label, bool $ok, string $message): array
    {
        return ['label' => $label, 'ok' => $ok, 'message' => $ok ? '通过' : $message];
    }

    private function ensureWritableDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return is_dir($path) && is_writable($path);
    }

    private function root(): string
    {
        $root = $this->rootPath ?? app()->getRootPath();
        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function stateStore(): UpdateStateStore
    {
        return $this->stateStore ?? new UpdateStateStore($this->root());
    }
}
```

- [ ] **Step 5: Verify GREEN**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdatePreflightServiceTest.php
```

Expected: `OK`.

- [ ] **Step 6: Commit**

```powershell
git add app/service/update/UpdateStateStore.php app/service/update/UpdatePreflightService.php tests/UpdatePreflightServiceTest.php
git commit -m "feat: add update preflight checks"
```

---

### Task 4: Package Download, Checksum, And Zip Validation

**Files:**
- Create: `app/service/update/UpdatePackageService.php`
- Create: `tests/UpdatePackageServiceTest.php`

- [ ] **Step 1: Write failing package tests**

Create `tests/UpdatePackageServiceTest.php` with tests for valid package, checksum mismatch, missing manifest, and zip-slip paths. Use `ZipArchive` to create temporary packages. The core assertions:

```php
$result = $service->verifyAndExtract($zipPath, $shaPath, 'v2.1.2');
self::assertSame('v2.1.2', $result['tag_name']);
self::assertDirectoryExists($result['package_root']);
self::assertFileExists($result['package_root'] . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php');
```

For checksum mismatch:

```php
file_put_contents($shaPath, str_repeat('0', 64) . "  " . basename($zipPath));
$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('SHA256');
$service->verifyAndExtract($zipPath, $shaPath, 'v2.1.2');
```

For zip-slip:

```php
$this->createZip($zipPath, [
    'vpay-v2.1.2/release-manifest.json' => '{"name":"vpay","version":"v2.1.2","app_version":"2.1.2"}',
    '../evil.php' => '<?php',
]);
$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('非法路径');
$service->verifyAndExtract($zipPath, $shaPath, 'v2.1.2');
```

- [ ] **Step 2: Run test and verify RED**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdatePackageServiceTest.php
```

Expected: class not found for `UpdatePackageService`.

- [ ] **Step 3: Implement package service**

Create `app/service/update/UpdatePackageService.php` with these public methods:

```php
public function download(array $assets, string $tagName): array
public function verifyAndExtract(string $zipPath, string $sha256Path, string $tagName): array
```

Implementation requirements:

- Store downloads in `runtime/update/downloads/`.
- Store extracted packages in `runtime/update/extracted/<tag>/`.
- Write downloads to `.part` files before renaming.
- Verify `hash_file('sha256', $zipPath)` against the first 64 hex chars in the sha256 file.
- Reject zip entries containing `../`, `..\`, absolute paths, or Windows drive prefixes.
- Require package root `vpay-<tag>/`.
- Require `release-manifest.json`, `config/app.php`, `public/index.php`, `public/index.html`, `vendor/autoload.php`, and `database/migrations/`.
- Require manifest `version` equals `$tagName`.
- Require manifest `app_version` equals `ltrim($tagName, 'vV')`.

- [ ] **Step 4: Verify GREEN**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdatePackageServiceTest.php
```

Expected: `OK`.

- [ ] **Step 5: Commit**

```powershell
git add app/service/update/UpdatePackageService.php tests/UpdatePackageServiceTest.php
git commit -m "feat: verify update packages"
```

---

### Task 5: File Backup Service

**Files:**
- Create: `app/service/update/UpdateBackupService.php`
- Create: `tests/UpdateBackupServiceTest.php`

- [ ] **Step 1: Write failing backup tests**

Create `tests/UpdateBackupServiceTest.php`. It should build a temporary project root with `app/AppService.php`, `config/app.php`, `public/index.php`, `.env`, `runtime/cache.tmp`, and `runtime/update/downloads/package.zip`.

Core assertions:

```php
$backup = (new UpdateBackupService($root))->backup('2.1.1', '2.1.2');
self::assertFileExists($backup['path']);

$entries = $this->zipEntries($backup['path']);
self::assertContains('app/AppService.php', $entries);
self::assertContains('config/app.php', $entries);
self::assertContains('.env', $entries);
self::assertNotContains('runtime/cache.tmp', $entries);
self::assertNotContains('runtime/update/downloads/package.zip', $entries);
```

- [ ] **Step 2: Run test and verify RED**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdateBackupServiceTest.php
```

Expected: class not found for `UpdateBackupService`.

- [ ] **Step 3: Implement backup service**

Create `app/service/update/UpdateBackupService.php`.

Public API:

```php
public function backup(string $fromVersion, string $targetVersion): array
```

Backup filename:

```php
'v' . $targetVersion . '-from-v' . $fromVersion . '-' . date('Ymd-His') . '.zip'
```

Include:

```php
['app', 'config', 'database', 'extend', 'public', 'route', 'vendor', 'view']
['.env', 'composer.json', 'composer.lock', 'think', 'vmq.sql', 'release-manifest.json']
```

Exclude:

```php
['runtime', 'public/runtime', 'runtime/update', 'runtime/install/lock.json']
```

Every zip entry must use `/` separators.

- [ ] **Step 4: Verify GREEN**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdateBackupServiceTest.php
```

Expected: `OK`.

- [ ] **Step 5: Commit**

```powershell
git add app/service/update/UpdateBackupService.php tests/UpdateBackupServiceTest.php
git commit -m "feat: backup files before updates"
```

---

### Task 6: Apply Update And Reuse MigrationRunner

**Files:**
- Create: `app/service/update/UpdateApplyService.php`
- Create: `tests/UpdateApplyServiceTest.php`

- [ ] **Step 1: Write failing apply tests**

Create `tests/UpdateApplyServiceTest.php` with a temporary current root and extracted package root.

Required assertions:

```php
$result = $service->apply([
    'from_version' => '2.1.1',
    'target_version' => '2.1.2',
    'package_root' => $packageRoot,
    'backup_path' => $backupPath,
]);

self::assertSame('updated', $result['status']);
self::assertSame('new code', file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'AppService.php'));
self::assertSame('APP_DEBUG=false', file_get_contents($root . DIRECTORY_SEPARATOR . '.env'));
self::assertSame(['2.1.1', '2.1.2'], $migrationCall);
self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'update.lock');
```

Add a failure test where the injected migration callable throws `RuntimeException('migration failed')`, then assert `last-error.json` contains stage `migrate` and the lock is cleared or marked failed consistently.

- [ ] **Step 2: Run test and verify RED**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdateApplyServiceTest.php
```

Expected: class not found for `UpdateApplyService`.

- [ ] **Step 3: Implement apply service**

Create `app/service/update/UpdateApplyService.php`.

Constructor:

```php
public function __construct(
    private readonly ?string $rootPath = null,
    private readonly ?UpdateStateStore $stateStore = null,
    private readonly mixed $migrationRunner = null
) {
}
```

Public method:

```php
public function apply(array $context): array
```

Rules:

- Write lock before copying.
- Write status for `backup`, `copy`, `migrate`, and `complete`.
- Copy only managed release paths.
- Preserve `.env`, `runtime/`, `public/runtime/`, `runtime/update/`, and `runtime/install/`.
- Copy files through `<target>.update-tmp` and `rename()`.
- Call migration as:

```php
$runner = $this->migrationRunner;
if (is_callable($runner)) {
    $runner($fromVersion, $targetVersion);
} else {
    app()->make(\app\service\install\MigrationRunner::class)->runPending($fromVersion, $targetVersion);
}
```

- On exception, write `last-error.json` with `stage`, `message`, `backup_path`, `from_version`, and `target_version`, then rethrow.
- On success, write `last-success.json`, clear lock, and return `['status' => 'updated']`.

- [ ] **Step 4: Verify GREEN**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\UpdateApplyServiceTest.php
```

Expected: `OK`.

- [ ] **Step 5: Commit**

```powershell
git add app/service/update/UpdateApplyService.php tests/UpdateApplyServiceTest.php
git commit -m "feat: apply verified update packages"
```

---

### Task 7: Admin Update Controller And Routes

**Files:**
- Create: `app/controller/admin/Update.php`
- Modify: `route/admin.php`
- Modify: `app/controller/Admin.php`
- Create: `tests/AdminUpdateControllerTest.php`

- [ ] **Step 1: Write failing controller tests**

Create `tests/AdminUpdateControllerTest.php`.

Test the controller directly with fake services:

```php
$controller = new Update($this->app);
$this->app->bind(\app\service\update\UpdateReleaseService::class, fn () => new class {
    public function check(): array
    {
        return ['status' => 'up_to_date', 'current_version' => '2.1.1'];
    }
});

$response = $controller->check();
$payload = json_decode((string) $response->getContent(), true);
self::assertSame(1, $payload['code']);
self::assertSame('up_to_date', $payload['data']['status']);
```

Add tests for `preflight()`, `status()`, and `recover()` with fake service bindings.

- [ ] **Step 2: Run test and verify RED**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\AdminUpdateControllerTest.php
```

Expected: class not found for `app\controller\admin\Update`.

- [ ] **Step 3: Implement controller**

Create `app/controller/admin/Update.php`:

```php
<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\controller\trait\ApiResponse;
use app\service\update\UpdateApplyService;
use app\service\update\UpdateBackupService;
use app\service\update\UpdatePackageService;
use app\service\update\UpdatePreflightService;
use app\service\update\UpdateReleaseService;
use app\service\update\UpdateStateStore;

class Update extends BaseController
{
    use ApiResponse;

    public function check()
    {
        return $this->success($this->app->make(UpdateReleaseService::class)->check());
    }

    public function preflight()
    {
        $release = (array) $this->request->post('release', []);
        return $this->success($this->app->make(UpdatePreflightService::class)->check($release));
    }

    public function start()
    {
        try {
            $release = (array) $this->request->post('release', []);
            $package = $this->app->make(UpdatePackageService::class)->download($release['assets'] ?? [], (string) ($release['tag_name'] ?? ''));
            $backup = $this->app->make(UpdateBackupService::class)->backup((string) config('app.ver'), ltrim((string) ($release['tag_name'] ?? ''), 'vV'));
            $result = $this->app->make(UpdateApplyService::class)->apply($package + [
                'from_version' => (string) config('app.ver'),
                'target_version' => ltrim((string) ($release['tag_name'] ?? ''), 'vV'),
                'backup_path' => $backup['path'],
            ]);

            return $this->success($result, '更新完成');
        } catch (\Throwable $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function status()
    {
        return $this->success($this->app->make(UpdateStateStore::class)->status());
    }

    public function recover()
    {
        return $this->success($this->app->make(UpdateStateStore::class)->lastError());
    }
}
```

- [ ] **Step 4: Route update endpoints**

Modify `route/admin.php` inside the `admin/index` group:

```php
Route::any('checkUpdate', 'admin.Update/check');
Route::post('preflightUpdate', 'admin.Update/preflight');
Route::post('startUpdate', 'admin.Update/start');
Route::any('getUpdateStatus', 'admin.Update/status');
Route::any('getUpdateRecovery', 'admin.Update/recover');
```

Remove or stop routing to the old `Admin::checkUpdate()` method. Leave the old method in place only if removing it causes unrelated compatibility failures; if kept, make it delegate to `admin\Update`.

- [ ] **Step 5: Verify GREEN**

Run:

```powershell
.\vendor\bin\phpunit.bat tests\AdminUpdateControllerTest.php
```

Expected: `OK`.

- [ ] **Step 6: Commit**

```powershell
git add app/controller/admin/Update.php route/admin.php app/controller/Admin.php tests/AdminUpdateControllerTest.php
git commit -m "feat: expose admin update api"
```

---

### Task 8: Frontend Update Card

**Files:**
- Create: `frontend/admin/src/api/admin/update.ts`
- Create: `frontend/admin/src/views/system/settings/updateState.ts`
- Create: `frontend/admin/src/views/system/settings/components/SystemUpdateCard.vue`
- Modify: `frontend/admin/src/views/system/settings/index.vue`
- Create: `frontend/admin/tests/systemUpdateState.test.ts`

- [ ] **Step 1: Write failing frontend state tests**

Create `frontend/admin/tests/systemUpdateState.test.ts`:

```ts
import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  canStartUpdate,
  normalizePreflightChecks,
  updateBadgeType
} from "../src/views/system/settings/updateState";

describe("system update state", () => {
  it("only allows update when release is available and preflight passes", () => {
    assert.equal(canStartUpdate("update_available", true, false), true);
    assert.equal(canStartUpdate("up_to_date", true, false), false);
    assert.equal(canStartUpdate("update_available", false, false), false);
    assert.equal(canStartUpdate("update_available", true, true), false);
  });

  it("maps status to element plus badge types", () => {
    assert.equal(updateBadgeType("update_available"), "warning");
    assert.equal(updateBadgeType("up_to_date"), "success");
    assert.equal(updateBadgeType("check_failed"), "danger");
  });

  it("normalizes preflight checks", () => {
    assert.deepEqual(
      normalizePreflightChecks([{ label: "ZipArchive", ok: false, message: "缺失" }]),
      [{ label: "ZipArchive", ok: false, message: "缺失" }]
    );
  });
});
```

- [ ] **Step 2: Run frontend test and verify RED**

Run:

```powershell
Set-Location frontend\admin
node --experimental-strip-types --test tests/systemUpdateState.test.ts
```

Expected: import failure for missing `updateState`.

- [ ] **Step 3: Implement frontend API**

Create `frontend/admin/src/api/admin/update.ts`:

```ts
import { http } from "@/utils/http";

export type UpdateCheckResponse = {
  status: string;
  message: string;
  current_version?: string;
  latest_version?: string;
  tag_name?: string;
  release_url?: string;
  published_at?: string;
  body?: string;
  assets?: Record<string, { name: string; download_url: string; size: number }>;
};

export const checkUpdate = () =>
  http.request<{ code: number; msg: string; data: UpdateCheckResponse }>(
    "get",
    "/admin/index/checkUpdate"
  );

export const preflightUpdate = (release: UpdateCheckResponse) =>
  http.request<{ code: number; msg: string; data: any }>(
    "post",
    "/admin/index/preflightUpdate",
    { data: { release } }
  );

export const startUpdate = (release: UpdateCheckResponse) =>
  http.request<{ code: number; msg: string; data: any }>(
    "post",
    "/admin/index/startUpdate",
    { data: { release } }
  );

export const getUpdateStatus = () =>
  http.request<{ code: number; msg: string; data: any }>(
    "get",
    "/admin/index/getUpdateStatus"
  );

export const getUpdateRecovery = () =>
  http.request<{ code: number; msg: string; data: any }>(
    "get",
    "/admin/index/getUpdateRecovery"
  );
```

- [ ] **Step 4: Implement pure UI state helpers**

Create `frontend/admin/src/views/system/settings/updateState.ts`:

```ts
export type PreflightCheck = {
  label: string;
  ok: boolean;
  message: string;
};

export function canStartUpdate(
  status: string,
  preflightOk: boolean,
  updating: boolean
): boolean {
  return status === "update_available" && preflightOk && !updating;
}

export function updateBadgeType(status: string): "success" | "warning" | "danger" | "info" {
  if (status === "up_to_date") return "success";
  if (status === "update_available") return "warning";
  if (status === "check_failed") return "danger";
  return "info";
}

export function normalizePreflightChecks(input: unknown): PreflightCheck[] {
  if (!Array.isArray(input)) return [];
  return input.map(item => {
    const row = item as Partial<PreflightCheck>;
    return {
      label: String(row.label ?? ""),
      ok: row.ok === true,
      message: String(row.message ?? "")
    };
  });
}
```

- [ ] **Step 5: Implement SystemUpdateCard**

Create `frontend/admin/src/views/system/settings/components/SystemUpdateCard.vue` with:

- Check update button.
- Preflight button.
- Start update confirmation dialog.
- Preflight result list.
- Recovery error display.
- Element Plus card styling consistent with existing settings cards.

Use `checkUpdate`, `preflightUpdate`, `startUpdate`, and `getUpdateRecovery` from the new API file.

- [ ] **Step 6: Mount card in settings page**

Modify `frontend/admin/src/views/system/settings/index.vue`:

```ts
import SystemUpdateCard from "./components/SystemUpdateCard.vue";
```

Add after the top settings card:

```vue
<SystemUpdateCard />
```

- [ ] **Step 7: Verify frontend**

Run:

```powershell
Set-Location frontend\admin
pnpm typecheck
pnpm build
```

Expected: both commands exit `0`.

- [ ] **Step 8: Commit**

```powershell
git add frontend/admin/src/api/admin/update.ts frontend/admin/src/views/system/settings/updateState.ts frontend/admin/src/views/system/settings/components/SystemUpdateCard.vue frontend/admin/src/views/system/settings/index.vue frontend/admin/tests/systemUpdateState.test.ts
git commit -m "feat(admin): add system update card"
```

---

### Task 9: End-To-End Verification

**Files:**
- Modify: `README-INSTALL.md`
- Modify: `docs/superpowers/specs/2026-04-25-admin-auto-update-design.md` only if implementation deviates from the approved design

- [ ] **Step 1: Document update behavior**

Add a short section to `README-INSTALL.md`:

```md
## 后台自动更新

管理员登录后台后可以在“系统设置”中检查 GitHub Release 更新。自动更新会先执行环境预检，下载 Release 包和 `.sha256` 校验文件，通过校验后备份当前程序文件，再覆盖新版文件并执行数据库 migration。

自动更新会保留 `.env`、`runtime/` 和运行状态目录。执行更新前仍建议先备份数据库。
```

- [ ] **Step 2: Run backend test suite**

Run:

```powershell
.\vendor\bin\phpunit.bat
```

Expected: `OK`.

- [ ] **Step 3: Run frontend verification**

Run:

```powershell
Set-Location frontend\admin
pnpm typecheck
pnpm build
```

Expected: both commands exit `0`.

- [ ] **Step 4: Run release package command locally**

Run:

```powershell
php build\release-package.php --version=v9.9.9 --output=build\releases
```

Expected:

- `build/releases/vpay-v9.9.9/release-manifest.json` exists.
- Manifest includes `app_version`.
- Package includes `public/index.html`.

- [ ] **Step 5: Commit docs and any final fixes**

```powershell
git add README-INSTALL.md docs/superpowers/specs/2026-04-25-admin-auto-update-design.md
git commit -m "docs: document admin auto updates"
```

- [ ] **Step 6: Final status**

Run:

```powershell
git status -sb
git log --oneline -10
```

Expected: working tree is clean except ignored local build artifacts, and recent commits show each task in order.
