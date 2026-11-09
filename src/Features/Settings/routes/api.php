<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Http\Controllers\ModuleSettingsController;
use Illuminate\Support\Facades\Route;

$registerSettingsRoutes = function (string $namePrefix): void {
    Route::get('/', [
        ModuleSettingsController::class,
        'index',
    ])->name($namePrefix . 'index');

    Route::get('/{module}', [
        ModuleSettingsController::class,
        'show',
    ])->name($namePrefix . 'show');

    Route::put('/{module}', [
        ModuleSettingsController::class,
        'update',
    ])->name($namePrefix . 'update');
};

Route::prefix('settings')
    ->middleware(['api', 'auth:api'])
    ->group(fn () => $registerSettingsRoutes('settings.'));

Route::prefix('api/settings')
    ->middleware(['api', 'auth:api'])
    ->group(fn () => $registerSettingsRoutes('api.settings.'));
