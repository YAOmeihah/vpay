<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

// 测试路由
Route::any('test', 'index/test');

// 支付系统路由 - 保持与原项目完全一致的API接口
Route::any('login', 'index/login');
Route::any('getMenu', 'index/getMenu')->middleware(\app\middleware\AdminAuth::class);
Route::any('createOrder', 'index/createOrder');
Route::any('getOrder', 'index/getOrder');
Route::any('checkOrder', 'index/checkOrder');
Route::any('closeOrder', 'index/closeOrder');
Route::any('getState', 'index/getState');
Route::any('appHeart', 'index/appHeart');
Route::any('appPush', 'index/appPush');
Route::any('closeEndOrder', 'index/closeEndOrder');
Route::post('mapi.php', 'epay/mapi');
Route::rule('submit.php', 'epay/submit', 'GET|POST');
Route::post('api/pay/create', 'epay/createV2');
Route::post('api/pay/submit', 'epay/submitV2');

// Admin后台管理路由（需要登录）
Route::group('admin/index', function () {
    Route::any('getMain', 'admin/getMain');
    Route::any('checkUpdate', 'admin/checkUpdate');
    Route::any('getSettings', 'admin/getSettings');
    Route::any('saveSetting', 'admin/saveSetting');
    Route::any('generateRsaKeys', 'admin/generateRsaKeys');
    Route::any('addPayQrcode', 'admin/addPayQrcode');
    Route::any('getPayQrcodes', 'admin/getPayQrcodes');
    Route::any('delPayQrcode', 'admin/delPayQrcode');
    Route::any('getOrders', 'admin/getOrders');
    Route::any('delOrder', 'admin/delOrder');
    Route::any('setBd', 'admin/setBd');
    Route::any('delGqOrder', 'admin/delGqOrder');
    Route::any('delLastOrder', 'admin/delLastOrder');
})->middleware(\app\middleware\AdminAuth::class);

// 二维码生成（无需登录）
Route::any('enQrcode', 'admin/enQrcode');
