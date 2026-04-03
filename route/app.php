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
Route::any('getMenu', 'index/getMenu');
Route::any('createOrder', 'index/createOrder');
Route::any('getOrder', 'index/getOrder');
Route::any('checkOrder', 'index/checkOrder');
Route::any('closeOrder', 'index/closeOrder');
Route::any('getState', 'index/getState');
Route::any('appHeart', 'index/appHeart');
Route::any('appPush', 'index/appPush');
Route::any('closeEndOrder', 'index/closeEndOrder');
Route::post('mapi.php', 'epay/mapi');
Route::post('submit.php', 'epay/submit');

// Admin后台管理路由
Route::any('admin/index/getMain', 'admin/getMain');
Route::any('admin/index/checkUpdate', 'admin/checkUpdate');
Route::any('admin/index/getSettings', 'admin/getSettings');
Route::any('admin/index/saveSetting', 'admin/saveSetting');
Route::any('admin/index/addPayQrcode', 'admin/addPayQrcode');
Route::any('admin/index/getPayQrcodes', 'admin/getPayQrcodes');
Route::any('admin/index/delPayQrcode', 'admin/delPayQrcode');
Route::any('admin/index/getOrders', 'admin/getOrders');
Route::any('admin/index/delOrder', 'admin/delOrder');
Route::any('admin/index/setBd', 'admin/setBd');
Route::any('admin/index/delGqOrder', 'admin/delGqOrder');
Route::any('admin/index/delLastOrder', 'admin/delLastOrder');
Route::any('enQrcode', 'admin/enQrcode');
