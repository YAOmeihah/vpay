<?php
// +----------------------------------------------------------------------
// | 安全配置
// +----------------------------------------------------------------------

return [
    // 登录安全
    'login' => [
        // 最大登录失败次数
        'max_attempts' => 5,
        // 锁定时间（秒）
        'lockout_time' => 1800, // 30分钟
        // Session超时时间（秒）
        'session_timeout' => 7200, // 2小时
        // 是否检查登录IP
        'check_ip' => true,
    ],

    // 输入验证
    'validation' => [
        // 最大字符串长度
        'max_string_length' => 500,
        // 订单号最大长度
        'max_order_id_length' => 64,
        // 最大金额
        'max_amount' => 99999.99,
        // 最小金额
        'min_amount' => 0.01,
    ],

    // 文件上传安全
    'upload' => [
        // 允许的文件类型
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
        // 最大文件大小（字节）
        'max_size' => 2097152, // 2MB
        // 上传目录
        'upload_path' => 'uploads/',
    ],

    // HTTP安全头
    'headers' => [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net lib.baomitu.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:;",
    ],

    // 敏感操作日志
    'audit_log' => [
        'enabled' => true,
        'log_file' => 'security.log',
        'events' => [
            'login_success',
            'login_failed',
            'admin_action',
            'order_create',
            'setting_change',
        ],
    ],
];
