<?php

use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::prefix('calculateur')->name('calculator.')->group(function () {
    Route::get('/', [CalculatorController::class, 'index'])->name('index');
    Route::get('/calculer', [CalculatorController::class, 'resultatIndisponible'])->name('calculer.unavailable');
    Route::post('/calculer', [CalculatorController::class, 'calculer'])->name('calculer');
});

Route::get('/documentation', [DocumentationController::class, 'index'])->name('documentation');
Route::get('/lang/{locale}', [LocaleController::class, 'update'])->name('locale.update');
