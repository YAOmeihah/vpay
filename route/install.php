<?php

use think\facade\Route;

Route::any('install/check', 'install.Wizard/check');
Route::post('install/run', 'install.Wizard/run');
Route::any('install/recover', 'install.Wizard/recover');
Route::any('install', 'install.Wizard/index');
