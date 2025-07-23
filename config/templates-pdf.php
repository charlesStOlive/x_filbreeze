<?php

return [
    'types' => [
        \App\Models\Invoice::class => 'invoice',
        \App\Models\Company::class => 'company',
    ],

    'invoice' => [
        'default' => \App\Services\Pdf\Templates\Invoice\InvoiceSummaryPdfTemplate::class,
        'templates' => [
            \App\Services\Pdf\Templates\Invoice\InvoiceSummaryPdfTemplate::class,
            \App\Services\Pdf\Templates\Invoice\InvoiceBasePdfTemplate::class,
            \App\Services\Pdf\Templates\Invoice\InvoiceComplete::class,
            \App\Services\Pdf\Templates\Invoice\InvoiceNdaPdfTemplate::class,
        ],
    ],

    'company' => [
        'default' => \App\Services\Pdf\Templates\Company\CompanyNdaPdfTemplate::class,
        'templates' => [
            \App\Services\Pdf\Templates\Company\CompanyNdaPdfTemplate::class,
        ],
    ],
];
