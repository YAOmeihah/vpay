<?php

use think\facade\Route;

Route::any('createOrder', 'merchant.Order/createOrder');
Route::any('getOrder', 'merchant.Order/getOrder');
Route::any('selectOrderPayType', 'merchant.Order/selectOrderPayType');
Route::any('checkOrder', 'merchant.Order/checkOrder');
Route::any('closeOrder', 'merchant.Order/closeOrder');
