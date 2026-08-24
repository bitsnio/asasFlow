<?php

use Bitsnio\AsasFlow\Features\Cache\Http\Controllers\CacheManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('asasflow/cache')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        
        Route::get('/status', [CacheManagementController::class, 'status'])
            ->name('asasflow.cache.status');
        
        Route::post('/flush', [CacheManagementController::class, 'flush'])
            ->name('asasflow.cache.flush');
        
        Route::post('/flush/{module}', [CacheManagementController::class, 'flushModule'])
            ->name('asasflow.cache.flush.module');
        
        Route::post('/warm/{module}', [CacheManagementController::class, 'warmModule'])
            ->name('asasflow.cache.warm.module');
    });
