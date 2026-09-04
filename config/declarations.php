<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Première période gérée par l'application
    |--------------------------------------------------------------------------
    |
    | Les périodes antérieures ont été déclarées manuellement. Ces valeurs
    | servent uniquement lorsqu'aucune déclaration du type concerné n'existe.
    |
    */
    'start_from' => [
        'vat' => env('DECLARATIONS_VAT_START_FROM', '2026-01-01'),
        'urssaf' => env('DECLARATIONS_URSSAF_START_FROM', '2026-01-01'),
    ],
];
