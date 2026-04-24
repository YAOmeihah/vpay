<?php
declare(strict_types=1);

namespace app\service\install;

use app\model\Setting;
use think\facade\Db;

class InstallStateService
{
    private const LEGACY_BASELINE_VERSION = '2.0.0';

    public function status(): array
    {
        $runtimePath = $this->installRuntimePath();
        $enableFlag = is_file($runtimePath . DIRECTORY_SEPARATOR . 'enable.flag');
        $lockFile = $runtimePath . DIRECTORY_SEPARATOR . 'lock.json';
        $lastErrorFile = $runtimePath . DIRECTORY_SEPARATOR . 'last-error.json';

        if (is_file($lockFile)) {
            return ['state' => 'locked', 'message' => '安装或升级正在执行'];
        }

        if (is_file($lastErrorFile)) {
            return ['state' => 'recovery_required', 'message' => '安装或升级失败，等待恢复'];
        }

        if (!$this->settingsTableAvailable()) {
            return [
                'state' => $enableFlag ? 'not_installed' : 'installed',
                'message' => $enableFlag ? '系统尚未安装' : '系统状态未知',
            ];
        }

        $installStatus = Setting::getConfigValue('install_status');
        $schemaVersion = Setting::getConfigValue('schema_version');
        $appVersion = (string) config('app.ver', '');

        if ($installStatus === '' && !$enableFlag && $this->looksLikeLegacyInstalledSystem()) {
            return [
                'state' => 'upgrade_required',
                'message' => '检测到旧版系统，需要升级',
                'current_version' => self::LEGACY_BASELINE_VERSION,
                'target_version' => $appVersion,
            ];
        }

        if ($installStatus === '' || $installStatus === 'pending') {
            return [
                'state' => $enableFlag ? 'not_installed' : 'recovery_required',
                'message' => $installStatus === 'pending' ? '系统安装未完成' : '系统尚未安装',
            ];
        }

        if ($schemaVersion !== '' && $appVersion !== '' && version_compare($schemaVersion, $appVersion, '<')) {
            return [
                'state' => 'upgrade_required',
                'message' => '系统待升级',
                'current_version' => $schemaVersion,
                'target_version' => $appVersion,
            ];
        }

        return ['state' => 'installed', 'message' => '系统已安装'];
    }

    protected function installRuntimePath(): string
    {
        return app()->getRuntimePath() . 'install';
    }

    protected function settingsTableAvailable(): bool
    {
        try {
            return Db::query("SHOW TABLES LIKE 'setting'") !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    private function looksLikeLegacyInstalledSystem(): bool
    {
        $markers = [
            Setting::getConfigValue('user'),
            Setting::getConfigValue('pass'),
            Setting::getConfigValue('key'),
        ];

        foreach ($markers as $marker) {
            if (trim($marker) !== '') {
                return true;
            }
        }

        return false;
    }
}
