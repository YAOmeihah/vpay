<?php
declare(strict_types=1);

namespace app\service\update;

use RuntimeException;

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

    public function resolveUpdate(string $requestedTag = ''): array
    {
        $result = $this->checkFromRelease($this->client()->latest());
        $tag = (string) ($result['tag_name'] ?? '');

        if ($requestedTag !== '' && !hash_equals($tag, $requestedTag)) {
            throw new RuntimeException('请求的更新版本不是当前最新正式版本');
        }

        if (($result['status'] ?? '') !== 'update_available') {
            throw new RuntimeException((string) ($result['message'] ?? '当前没有可用更新'));
        }

        if (($result['assets'] ?? []) === []) {
            throw new RuntimeException('Release 缺少可下载的更新包');
        }

        return $result;
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

        $status = 'update_available';
        if (version_compare($current, $latest, '=')) {
            $status = 'up_to_date';
        } elseif (version_compare($current, $latest, '>')) {
            $status = 'ahead';
        }

        $zipName = 'vpay-' . $tag . '.zip';
        $shaName = $zipName . '.sha256';
        $zip = $this->assetByName((array) ($release['assets'] ?? []), $zipName);
        $sha = $this->assetByName((array) ($release['assets'] ?? []), $shaName);
        if ($status === 'update_available' && $zip === null) {
            return $this->failed('Release 缺少安装包: ' . $zipName, $current, $tag);
        }
        if ($status === 'update_available' && $sha === null) {
            return $this->failed('Release 缺少 sha256 校验文件: ' . $shaName, $current, $tag);
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
            'assets' => $zip !== null && $sha !== null ? [
                'zip' => $this->assetPayload($zip),
                'sha256' => $this->assetPayload($sha),
            ] : [],
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
