<?php
declare(strict_types=1);

namespace app\service\runtime;

interface MonitorState
{
    public function getLastHeartbeatAt(): int;

    public function getLastPaidAt(): int;

    public function markHeartbeatAt(int $timestamp): void;

    public function markPaidAt(int $timestamp): void;

    public function markOnline(): void;

    public function markOffline(): void;

    public function isOnline(): bool;
}
