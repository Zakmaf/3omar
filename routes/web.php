<?php

use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::prefix('calculateur')->name('calculator.')->group(function () {
    Route::get('/', [CalculatorController::class, 'index'])->name('index');
    Route::post('/calculer', [CalculatorController::class, 'calculer'])->name('calculer');
});

Route::get('/documentation', [DocumentationController::class, 'index'])->name('documentation');
