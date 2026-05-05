<?php

use think\facade\Route;

Route::post('maintenance/run', 'maintenance.Cron/run')
    ->option(['response_type' => 'json']);
