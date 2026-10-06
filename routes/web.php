<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TrustController;
use App\Services\GuideService;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::prefix('calculateur')->name('calculator.')->group(function () {
    Route::get('/', [CalculatorController::class, 'index'])->name('index');
    Route::get('/calculer', [CalculatorController::class, 'resultatIndisponible'])->name('calculer.unavailable');
    Route::post('/calculer', [CalculatorController::class, 'calculer'])->name('calculer')->middleware('throttle:calculer');
    // La comparaison rejoue le moteur deux fois : même limitation que le calcul.
    Route::get('/comparer', [CalculatorController::class, 'comparer'])->name('comparer')->middleware('throttle:calculer');
});

Route::get('/documentation', [DocumentationController::class, 'index'])->name('documentation');
Route::get('/api-documentation', [ApiDocumentationController::class, 'index'])->name('api.documentation');
Route::get('/fiabilite', [TrustController::class, 'index'])->name('trust');
// Pages éditoriales thématiques (issue #117) : une URL par recherche courante.
foreach (array_keys(GuideService::GUIDES) as $slug) {
    Route::get('/'.$slug, [GuideController::class, 'show'])->defaults('slug', $slug)->name('guides.'.$slug);
}

Route::get('/lang/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/ads.txt', fn () => response(
    config('ads.publisher_id')
        ? 'google.com, '.config('ads.publisher_id').', DIRECT, f08c47fec0942fa0'
        : '',
    200,
    ['Content-Type' => 'text/plain'],
));
