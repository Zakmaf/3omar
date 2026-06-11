<?php

return [
    'enabled' => (bool) env('ADSENSE_ENABLED', false),
    'client' => env('ADSENSE_CLIENT'),

    'placements' => [
        'header' => [
            'slot' => env('ADSENSE_SLOT_HEADER'),
            'class' => 'ad-slot-horizontal',
        ],
        'footer' => [
            'slot' => env('ADSENSE_SLOT_FOOTER'),
            'class' => 'ad-slot-rectangle',
        ],
    ],
];
