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
        'session_timeout' => 28800, // 8小时
    ],

    // 分层请求频率限制
    'rate_limits' => [
        'default' => [
            'max_requests' => (int) env('SECURITY_RATE_LIMIT_DEFAULT_MAX_REQUESTS', 120),
            'window_seconds' => (int) env('SECURITY_RATE_LIMIT_DEFAULT_WINDOW_SECONDS', 60),
        ],
        'admin_login' => [
            'max_requests' => (int) env('SECURITY_RATE_LIMIT_ADMIN_LOGIN_MAX_REQUESTS', 5),
            'window_seconds' => (int) env('SECURITY_RATE_LIMIT_ADMIN_LOGIN_WINDOW_SECONDS', 1800),
        ],
        'admin_api' => [
            'max_requests' => (int) env('SECURITY_RATE_LIMIT_ADMIN_API_MAX_REQUESTS', 300),
            'window_seconds' => (int) env('SECURITY_RATE_LIMIT_ADMIN_API_WINDOW_SECONDS', 60),
        ],
        'merchant_api' => [
            'max_requests' => (int) env('SECURITY_RATE_LIMIT_MERCHANT_API_MAX_REQUESTS', 120),
            'window_seconds' => (int) env('SECURITY_RATE_LIMIT_MERCHANT_API_WINDOW_SECONDS', 60),
        ],
        'monitor_heartbeat' => [
            'max_requests' => (int) env('SECURITY_RATE_LIMIT_MONITOR_HEARTBEAT_MAX_REQUESTS', 30),
            'window_seconds' => (int) env('SECURITY_RATE_LIMIT_MONITOR_HEARTBEAT_WINDOW_SECONDS', 60),
        ],
        'monitor_push' => [
            'max_requests' => (int) env('SECURITY_RATE_LIMIT_MONITOR_PUSH_MAX_REQUESTS', 120),
            'window_seconds' => (int) env('SECURITY_RATE_LIMIT_MONITOR_PUSH_WINDOW_SECONDS', 60),
        ],
        'monitor_query' => [
            'max_requests' => (int) env('SECURITY_RATE_LIMIT_MONITOR_QUERY_MAX_REQUESTS', 60),
            'window_seconds' => (int) env('SECURITY_RATE_LIMIT_MONITOR_QUERY_WINDOW_SECONDS', 60),
        ],
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
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' cdn.jsdelivr.net lib.baomitu.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:;",
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
