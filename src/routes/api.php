<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Http\Controllers\SchemaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function () {
    Route::get('schema', [SchemaController::class, 'show'])->name('asasflow.schema.show');
});
