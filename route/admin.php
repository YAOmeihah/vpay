<?php

use app\middleware\AdminAuth;
use app\middleware\AdminCsrf;
use think\facade\Route;

Route::post('login', 'admin.Auth/login')->middleware(AdminCsrf::class);

Route::group('admin/index', function () {
    Route::any('getMain', 'admin.Dashboard/getMain');
    Route::any('checkUpdate', 'admin.Update/check');
    Route::post('preflightUpdate', 'admin.Update/preflight');
    Route::post('startUpdate', 'admin.Update/start');
    Route::any('getUpdateStatus', 'admin.Update/status');
    Route::any('getUpdateRecovery', 'admin.Update/recover');
    Route::any('getSettings', 'admin.Settings/getSettings');
    Route::post('saveSetting', 'admin.Settings/saveSetting');
    Route::post('generateMaintenanceToken', 'admin.Settings/generateMaintenanceToken');
    Route::post('testMaintenanceNotification', 'admin.Settings/testMaintenanceNotification');
    Route::any('getTerminals', 'admin.Terminal/getTerminals');
    Route::any('getTerminal', 'admin.Terminal/getTerminal');
    Route::post('saveTerminal', 'admin.Terminal/saveTerminal');
    Route::post('deleteTerminal', 'admin.Terminal/deleteTerminal');
    Route::post('toggleTerminal', 'admin.Terminal/toggleTerminal');
    Route::post('resetTerminalKey', 'admin.Terminal/resetTerminalKey');
    Route::any('getTerminalChannels', 'admin.Terminal/getTerminalChannels');
    Route::post('saveTerminalChannel', 'admin.Terminal/saveTerminalChannel');
    Route::post('toggleTerminalChannel', 'admin.Terminal/toggleTerminalChannel');
    Route::post('addPayQrcode', 'admin.Qrcode/addPayQrcode');
    Route::any('getPayQrcodes', 'admin.Qrcode/getPayQrcodes');
    Route::post('delPayQrcode', 'admin.Qrcode/delPayQrcode');
    Route::post('decodeQrcode', 'admin.Qrcode/decodeQrcode');
    Route::any('getOrders', 'admin.Order/getOrders');
    Route::post('createPaymentTestOrder', 'admin.PaymentTest/createPaymentTestOrder');
    Route::any('getPaymentTestOrder', 'admin.PaymentTest/getPaymentTestOrder');
    Route::any('getPaymentTestCallback', 'admin.PaymentTest/getPaymentTestCallback');
    Route::post('delOrder', 'admin.Order/delOrder');
    Route::post('setBd', 'admin.Order/setBd');
    Route::post('delGqOrder', 'admin.Order/delGqOrder');
    Route::post('delLastOrder', 'admin.Order/delLastOrder');
    Route::any('profile', 'admin.Profile/profile');
    Route::post('logout', 'admin.Profile/logout');
    Route::any('enQrcode', 'admin.Qrcode/enQrcode');
})->middleware(AdminAuth::class)
    ->middleware(AdminCsrf::class);
