<?php
declare(strict_types=1);

namespace app\service\admin;

class AdminPermissionService
{
    /**
     * @var array<int, string>
     */
    private const PERMISSIONS = [
        'dashboard:view',
        'settings:view',
        'settings:save',
        'monitor:view',
        'terminals:view',
        'terminals:save',
        'terminals:toggle',
        'channels:view',
        'channels:save',
        'channels:toggle',
        'qrcode:add',
        'qrcode:view',
        'qrcode:delete',
        'orders:view',
        'orders:delete',
        'orders:repair',
        'orders:cleanup',
    ];

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return self::PERMISSIONS;
    }
}
