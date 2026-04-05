<?php

use think\facade\Route;

Route::any('getState', 'monitor.Monitor/getState');
Route::any('appHeart', 'monitor.Monitor/appHeart');
Route::post('appPush', 'monitor.Monitor/appPush');
Route::any('closeEndOrder', 'monitor.Monitor/closeEndOrder');
