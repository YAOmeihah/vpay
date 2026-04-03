<?php
declare(strict_types=1);

namespace app\service\config;

interface SystemConfig
{
    public function getNotifyUrl(): string;

    public function getReturnUrl(): string;

    public function getSignKey(): string;

    public function getCloseMinutes(): int;

    public function getPayQfMode(): int;

    public function getWeChatPayUrl(): string;

    public function getAlipayPayUrl(): string;

    public function shouldVerifyNotifySsl(): bool;

    /**
     * @return array{enabled: bool, pid: string, key: string, name: string, private_key: string, public_key: string}
     */
    public function getEpayConfig(): array;
}
