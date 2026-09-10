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

    /*
    |--------------------------------------------------------------------------
    | Cadence des déclarations
    |--------------------------------------------------------------------------
    |
    | "monthly" ou "quarterly". Pilote la durée des périodes ouvertes par les
    | commandes automatiques (declarations:create-due, next()). La création
    | manuelle (popup) permet de choisir une cadence différente ponctuellement,
    | sans toucher à ce réglage.
    |
    */
    'periods' => [
        'vat' => env('DECLARATIONS_VAT_PERIOD', 'monthly'),
        'urssaf' => env('DECLARATIONS_URSSAF_PERIOD', 'quarterly'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Taux URSSAF estimé
    |--------------------------------------------------------------------------
    |
    | Utilisé pour estimer la somme due à l'URSSAF à partir du CA HT déclaré,
    | tant que la déclaration correspondante n'est pas marquée comme payée.
    |
    */
    'urssaf_rate' => (float) env('DECLARATIONS_URSSAF_RATE', 0.22),
];
