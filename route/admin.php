<?php

use think\facade\Route;

Route::any('login', 'admin.Auth/login');

Route::group('admin/index', function () {
    Route::any('getMain', 'admin/getMain');
    Route::any('checkUpdate', 'admin/checkUpdate');
    Route::any('getSettings', 'admin/getSettings');
    Route::any('saveSetting', 'admin/saveSetting');
    Route::any('getTerminals', 'admin/getTerminals');
    Route::post('saveTerminal', 'admin/saveTerminal');
    Route::post('toggleTerminal', 'admin/toggleTerminal');
    Route::post('resetTerminalKey', 'admin/resetTerminalKey');
    Route::any('getTerminalChannels', 'admin/getTerminalChannels');
    Route::post('saveTerminalChannel', 'admin/saveTerminalChannel');
    Route::post('toggleTerminalChannel', 'admin/toggleTerminalChannel');
    Route::any('addPayQrcode', 'admin/addPayQrcode');
    Route::any('getPayQrcodes', 'admin/getPayQrcodes');
    Route::any('delPayQrcode', 'admin/delPayQrcode');
    Route::post('decodeQrcode', 'admin/decodeQrcode');
    Route::any('getOrders', 'admin/getOrders');
    Route::any('delOrder', 'admin/delOrder');
    Route::any('setBd', 'admin/setBd');
    Route::any('delGqOrder', 'admin/delGqOrder');
    Route::any('delLastOrder', 'admin/delLastOrder');
    Route::any('profile', 'admin/profile');
    Route::post('logout', 'admin/logout');
})->middleware(\app\middleware\AdminAuth::class);

Route::any('enQrcode', 'admin/enQrcode');
