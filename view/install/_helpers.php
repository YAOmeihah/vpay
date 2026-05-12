<?php
declare(strict_types=1);

if (!function_exists('install_e')) {
    function install_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('install_state_label')) {
    function install_state_label(string $state): string
    {
        return match ($state) {
            'not_installed' => '待安装',
            'upgrade_required' => '待升级',
            'locked' => '执行中',
            'installed' => '已完成',
            default => '待检查',
        };
    }
}

if (!function_exists('install_mode_from_state')) {
    function install_mode_from_state(string $state): string
    {
        return match ($state) {
            'upgrade_required' => 'upgrade',
            'locked' => 'progress',
            default => 'install',
        };
    }
}

if (!function_exists('install_steps_for_mode')) {
    /**
     * @return array<int, string>
     */
    function install_steps_for_mode(string $mode): array
    {
        return match ($mode) {
            'upgrade' => ['环境检查', '版本确认', '管理员验证', '执行升级', '完成'],
            'progress' => ['环境检查', '准备执行', '执行中', '等待结果'],
            default => ['环境检查', '数据库配置', '管理员配置', '执行安装', '完成'],
        };
    }
}

if (!function_exists('install_shell_context')) {
    /**
     * @param array<string, mixed> $input
     * @return array{title: string, state: string, message: string, mode: string, active_step: int}
     */
    function install_shell_context(array $input): array
    {
        $state = (string) ($input['state'] ?? 'not_installed');
        $mode = (string) ($input['mode'] ?? install_mode_from_state($state));

        return [
            'title' => (string) ($input['title'] ?? '安装向导'),
            'state' => $state,
            'message' => (string) ($input['message'] ?? ''),
            'mode' => $mode,
            'active_step' => max(0, (int) ($input['active_step'] ?? 0)),
        ];
    }
}
