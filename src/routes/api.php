<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Http\Controllers\DropdownController;
use Bitsnio\AsasFlow\Http\Controllers\SchemaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:api'])->group(function () {
    Route::get('schema', [SchemaController::class, 'show'])->name('asasflow.schema.show');
    Route::get('api/schema', [SchemaController::class, 'show'])->name('asasflow.api.schema.show');

    Route::get('dropdown/{table}/{label?}/{value?}', DropdownController::class)
        ->name('asasflow.dropdown.index');

    Route::get('api/dropdown/{table}/{label?}/{value?}', DropdownController::class)
        ->name('asasflow.api.dropdown.index');
});
