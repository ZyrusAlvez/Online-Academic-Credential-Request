<?php

use App\Http\Controllers\Api\TurnaroundPredictionController;
use Illuminate\Support\Facades\Route;

/*
| Version 1 of the JSON API (spec 08). Registrar API tokens only; issue one with
| `php artisan app:api-token {email}`.
*/

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'role:registrar', 'throttle:60,1'])->group(function () {
    Route::post('predictions/turnaround', TurnaroundPredictionController::class)->name('predictions.turnaround');
});
