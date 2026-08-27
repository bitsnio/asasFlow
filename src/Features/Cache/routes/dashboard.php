<?php

use Bitsnio\AsasFlow\Features\Cache\Http\Controllers\CacheDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('asasflow-cache.dashboard.route_prefix', '_cache'))
    ->middleware(config('asasflow-cache.dashboard.middleware', ['web']))
    ->group(function () {
        
        Route::get('/', [CacheDashboardController::class, 'index'])
            ->name('asasflow-cache.dashboard');
        
        Route::get('/api/stats', [CacheDashboardController::class, 'stats'])
            ->name('asasflow-cache.api.stats');
        
        Route::get('/api/entries', [CacheDashboardController::class, 'entries'])
            ->name('asasflow-cache.api.entries');
        
        Route::post('/clear', [CacheDashboardController::class, 'clear'])
            ->name('asasflow-cache.clear');
    });
