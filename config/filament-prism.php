<?php

return [
    /*
     * Provider et modèle par défaut pour toute ressource qui ne précise pas les siens.
     * Les clés API (OPENAI_API_KEY...) se lisent dans config/prism.php.
     */
    'provider' => env('AI_PROVIDER', 'openai'),
    'model' => env('AI_MODEL', 'gpt-5.4-nano'),

    /*
     * Les ressources IA enregistrées (chacune une AiResource), retrouvées par leur clé.
     */
    'tasks' => [
        \CharlesStOlive\FilamentPrism\Tasks\OrthographyTask::class,
        \App\Ai\SupplierInvoiceExtraction::class,
        \App\Ai\EmailTranslation::class,
    ],

    /*
     * Extraction des factures fournisseurs (App\Ai\SupplierInvoiceExtraction).
     * `attach_document` : joindre aussi le PDF/l'image à l'appel, en plus du texte
     * scanné — plus fiable sur un scan de mauvaise qualité, mais plus de tokens.
     */
    'supplier_invoice_extraction' => [
        'model' => env('AI_EXTRACTION_MODEL', env('AI_MODEL', 'gpt-5.4-nano')),
        'attach_document' => (bool) env('AI_EXTRACTION_ATTACH_DOCUMENT', false),
    ],

    /*
     * Traduction des brouillons Outlook (App\Ai\EmailTranslation, via TradEmailProcessor).
     */
    'email_translation' => [
        'model' => env('AI_TRANSLATION_MODEL', env('AI_MODEL', 'gpt-5.4-nano')),
    ],
];
