<?php
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

// 测试路由
Route::any('test', 'index/test');

require __DIR__ . '/admin.php';
require __DIR__ . '/merchant.php';
require __DIR__ . '/monitor.php';
