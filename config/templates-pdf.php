<?php 

return [
    'types' => [
        \App\Models\Invoice::class => 'invoice',
        // \App\Models\Company::class => 'company',
    ],

    'invoice' => [
        'default' => \App\Services\Pdf\Templates\Invoice\InvoiceSummaryPdfTemplate::class,
        'templates' => [
            \App\Services\Pdf\Templates\Invoice\InvoiceSummaryPdfTemplate::class,
             \App\Services\Pdf\Templates\Invoice\InvoiceComplete::class,
        ],
    ],

    // 'company' => [
    //     'default' => \App\Services\Pdf\Templates\Company\CompanyOverviewPdfTemplate::class,
    //     'templates' => [
    //         \App\Services\Pdf\Templates\Company\CompanyOverviewPdfTemplate::class,
    //     ],
    // ],
];