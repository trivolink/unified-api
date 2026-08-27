<?php

use Illuminate\Support\Facades\Route;
use TrivoLink\UnifiedApi\Http\Controllers\TokenController;

Route::post(config('unified-api.token_endpoint.path', 'api/token'), [TokenController::class, 'store'])
    ->middleware((array) config('unified-api.token_endpoint.middleware', ['throttle:5,1']))
    ->name('unified-api.token');
