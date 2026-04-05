<?php
declare(strict_types=1);

namespace app\service\config;

interface SystemConfig
{
    public function getNotifyUrl(): string;

    public function getReturnUrl(): string;

    public function getSignKey(): string;

    public function getOrderCloseMinutes(): int;

    public function getOrderCloseRaw(): string;

    public function getPayQfMode(): string;

    public function getWeChatPayUrl(): string;

    public function getAlipayPayUrl(): string;

    public function getNotifySslVerifyEnabled(): bool;
}
