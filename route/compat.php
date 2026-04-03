<?php

use think\facade\Route;

Route::post('mapi.php', 'compat.Epay/mapi');
Route::rule('submit.php', 'compat.Epay/submit', 'GET|POST');
Route::post('api/pay/create', 'compat.Epay/createV2');
Route::post('api/pay/submit', 'compat.Epay/submitV2');
