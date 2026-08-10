<?php

return [
    'provider' => env('AI_PROVIDER', 'mistral'),
    'model' => env('AI_MODEL', 'mistral-large-latest'),

    'orthography' => [
        'model' => env('AI_ORTHOGRAPHY_MODEL', env('AI_MODEL', 'mistral-large-latest')),
    ],

    'translation' => [
        'model' => env('AI_TRANSLATION_MODEL', env('AI_MODEL', 'mistral-large-latest')),
    ],
];
