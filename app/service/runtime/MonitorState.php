<?php
declare(strict_types=1);

namespace app\service\runtime;

interface MonitorState
{
    public function getLastHeartbeat(): int;

    public function setLastHeartbeat(int $timestamp): void;

    public function getLastPayTime(): int;

    public function setLastPayTime(int $timestamp): void;

    public function isOnline(): bool;

    public function setOnline(bool $online): void;
}
