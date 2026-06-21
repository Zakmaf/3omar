<?php

return [
    'enabled' => (bool) env('ADSENSE_ENABLED', false),
    // Une variable vide (ex. .env.example) équivaut à « non configuré ».
    'client' => env('ADSENSE_CLIENT') ?: null,

    'placements' => [
        'header' => [
            'slot' => env('ADSENSE_SLOT_HEADER') ?: null,
            'class' => 'ad-slot-horizontal',
        ],
        'footer' => [
            'slot' => env('ADSENSE_SLOT_FOOTER') ?: null,
            'class' => 'ad-slot-rectangle',
        ],
    ],
];
