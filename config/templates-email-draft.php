<?php 

return [
    'types' => [
        \App\Models\Invoice::class => 'invoice',
        \App\Models\Company::class => 'company',],

    'invoice' => [
        'default' => \App\Services\MsGraph\EmailDraft\Templates\Invoice\InvoiceSummaryTemplate::class,
        'templates' => [
            \App\Services\MsGraph\EmailDraft\Templates\Invoice\InvoiceSummaryTemplate::class,
        ],
    ],

    'company' => [
        'default' => \App\Services\MsGraph\EmailDraft\Templates\Company\CompanyBaseTemplate::class,
        'templates' => [
            \App\Services\MsGraph\EmailDraft\Templates\Company\CompanyBaseTemplate::class,
        ],
    ],
];