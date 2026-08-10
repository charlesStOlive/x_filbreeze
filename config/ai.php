<?php

return [
    'provider' => env('AI_PROVIDER', 'openai'),
    'model' => env('AI_MODEL', 'gpt-5.4-nano'),

    'orthography' => [
        'model' => env('AI_ORTHOGRAPHY_MODEL', env('AI_MODEL', 'gpt-5.4-nano')),
    ],

    'translation' => [
        'model' => env('AI_TRANSLATION_MODEL', env('AI_MODEL', 'gpt-5.4-nano')),
    ],

    'extraction' => [
        'model' => env('AI_EXTRACTION_MODEL', env('AI_MODEL', 'gpt-5.4-nano')),
    ],
];
