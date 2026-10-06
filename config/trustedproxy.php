<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confiance
    |--------------------------------------------------------------------------
    |
    | Adresses ou plages CIDR, séparées par des virgules, dont Laravel accepte
    | les en-têtes X-Forwarded-*. Lu par le middleware TrustProxies du framework.
    |
    | Défaut sûr : aucune. Un client ne peut alors ni choisir son adresse (clé
    | des limiteurs de débit) ni se déclarer en HTTPS par un en-tête.
    |
    | L'image de release laisse cette variable vide : son Nginx résout déjà
    | l'adresse réelle et transmet HTTPS à PHP-FPM (docker/release/nginx.conf).
    | La valeur `*` (faire confiance au pair direct) reste possible mais doit
    | être posée explicitement. Voir docs/DEPLOIEMENT.md.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
