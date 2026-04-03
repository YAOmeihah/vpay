<?php

use think\facade\Route;

Route::any('getState', 'monitor.Monitor/getState');
Route::any('appHeart', 'monitor.Monitor/appHeart');
Route::any('appPush', 'monitor.Monitor/appPush');
Route::any('closeEndOrder', 'monitor.Monitor/closeEndOrder');
