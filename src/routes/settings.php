<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Core\Http\Controllers\ModuleSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')
    ->middleware(['auth:api'])
    ->group(function () {

        Route::get('/', [
            ModuleSettingsController::class,
            'index',
        ])->name('settings.index');

        Route::get('/{module}', [
            ModuleSettingsController::class,
            'show',
        ])->name('settings.show');

        Route::put('/{module}', [
            ModuleSettingsController::class,
            'update',
        ])->name('settings.update');

    });