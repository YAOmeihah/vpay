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

    public function lastSuccessPath(): string
    {
        return $this->updatePath() . DIRECTORY_SEPARATOR . 'last-success.json';
    }

    public function hasLock(): bool
    {
        return is_file($this->lockPath());
    }

    public function acquireLock(array $payload): bool
    {
        $this->ensureUpdatePath();
        $handle = @fopen($this->lockPath(), 'x');
        if ($handle === false) {
            return false;
        }

        try {
            fwrite($handle, $this->encode($payload + ['updated_at' => time()]));
        } finally {
            fclose($handle);
        }

        return true;
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
        $error = $this->readJson($this->lastErrorPath());
        if ($error !== [] && $this->hasNewerSuccessThanError($error)) {
            $this->clearLastError();

            return [];
        }

        return $error;
    }

    public function writeSuccess(array $payload): void
    {
        $this->ensureUpdatePath();
        file_put_contents($this->lastSuccessPath(), $this->encode($payload + ['created_at' => time()]));
        $this->clearLastError();
    }

    public function lastSuccess(): array
    {
        return $this->readJson($this->lastSuccessPath());
    }

    public function clearLastError(): void
    {
        if (is_file($this->lastErrorPath())) {
            @unlink($this->lastErrorPath());
        }
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

    private function hasNewerSuccessThanError(array $error): bool
    {
        $success = $this->lastSuccess();
        if ($success === []) {
            return false;
        }

        $successTime = (int) ($success['created_at'] ?? 0);
        $errorTime = (int) ($error['created_at'] ?? 0);
        if ($successTime > 0 && $errorTime > 0) {
            return $successTime >= $errorTime;
        }

        $successMtime = @filemtime($this->lastSuccessPath()) ?: 0;
        $errorMtime = @filemtime($this->lastErrorPath()) ?: 0;

        return $successMtime > 0 && $errorMtime > 0 && $successMtime >= $errorMtime;
    }

    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }
}
