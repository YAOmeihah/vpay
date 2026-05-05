<?php
use think\facade\Route;

require __DIR__ . '/install.php';
require __DIR__ . '/maintenance.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/merchant.php';
require __DIR__ . '/monitor.php';

Route::get('/', 'Index/index');
Route::get('enQrcode', 'admin.Qrcode/enQrcode');
Route::any('payment-test/notify', 'payment.TestCallback/notify');
Route::any('payment-test/return', 'payment.TestCallback/returnUrl');
