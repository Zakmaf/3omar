<?php

return [

    'name' => env('APP_NAME', 'Mon Bulletin de Paie Marocain'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => 'Africa/Casablanca',

    'locale' => 'fr',

    'fallback_locale' => 'fr',

    'faker_locale' => 'fr_MA',

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'maintenance' => ['driver' => 'file'],

    'providers' => Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
    ])->toArray(),

    'aliases' => Illuminate\Support\Facades\Facade::defaultAliases()->merge([
    ])->toArray(),

];
