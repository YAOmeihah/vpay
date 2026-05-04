<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动：生产可通过 CACHE_DRIVER=redis 启用 Redis
    'default' => env('CACHE_DRIVER', 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
        'redis' => [
            // 驱动方式
            'type'       => 'redis',
            // 服务器地址
            'host'       => env('CACHE_REDIS_HOST', '127.0.0.1'),
            // 端口
            'port'       => (int) env('CACHE_REDIS_PORT', 6379),
            // 密码
            'password'   => env('CACHE_REDIS_PASSWORD', ''),
            // 数据库
            'select'     => (int) env('CACHE_REDIS_SELECT', 0),
            // 超时时间
            'timeout'    => (float) env('CACHE_REDIS_TIMEOUT', 0),
            // 缓存前缀
            'prefix'     => env('CACHE_REDIS_PREFIX', 'vmq_'),
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 是否持久连接
            'persistent' => env('CACHE_REDIS_PERSISTENT', false),
            // 序列化机制
            'serialize'  => ['serialize', 'unserialize'],
        ],
        // 更多的缓存连接
    ],
];
