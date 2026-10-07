<?php

// Une variable vide (ex. .env.example) équivaut à « non configuré ».
$publisherId = env('ADSENSE_PUBLISHER_ID') ?: null;

return [
    'enabled' => (bool) env('ADSENSE_ENABLED', false),
    'publisher_id' => $publisherId,
    // L'identifiant client AdSense est l'identifiant éditeur préfixé par « ca- ».
    'client' => $publisherId ? 'ca-'.$publisherId : null,

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
