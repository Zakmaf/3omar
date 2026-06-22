<?php

use App\Http\Controllers\Api\V1\ApiCalculatorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('simuler/brut-vers-net', [ApiCalculatorController::class, 'simulerBrutVersNet'])
        ->middleware('throttle:api-simuler');

    Route::post('simuler/net-vers-brut', [ApiCalculatorController::class, 'simulerNetVersBrut'])
        ->middleware('throttle:api-simuler');

    Route::get('parametres', [ApiCalculatorController::class, 'parametres']);

    Route::get('health', [ApiCalculatorController::class, 'health']);
});
