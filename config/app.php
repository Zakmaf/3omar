<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    'name' => env('APP_NAME', '3omar'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'version' => env('APP_VERSION', 'V2.2.2'),

    'timezone' => 'Africa/Casablanca',

    'locale' => 'fr',

    'fallback_locale' => 'fr',

    'faker_locale' => 'fr_MA',

    'supported_locales' => [
        'fr' => ['label' => 'Français', 'short' => 'FR', 'dir' => 'ltr', 'intl' => 'fr-FR', 'og' => 'fr_MA'],
        'en' => ['label' => 'English', 'short' => 'EN', 'dir' => 'ltr', 'intl' => 'en-US', 'og' => 'en_US'],
        'ar' => ['label' => 'العربية', 'short' => 'AR', 'dir' => 'rtl', 'intl' => 'ar-MA', 'og' => 'ar_MA'],
        'es' => ['label' => 'Español', 'short' => 'ES', 'dir' => 'ltr', 'intl' => 'es-ES', 'og' => 'es_ES'],
    ],

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'maintenance' => ['driver' => 'file'],

    'providers' => ServiceProvider::defaultProviders()->merge([
        AppServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
    ])->toArray(),

];
