<?php

// Les exports Excel (MaatExports) de chaque modèle, comme templates-pdf.php et templates-email-draft.php : c'est aussi la
// liste de leurs permissions (DocumentPermissions).
return [
    'types' => [
        \App\Models\Product::class => 'product',
        \App\Models\Company::class => 'company',
    ],
    'product' => [
        'templates' => [
            \App\Services\MaatExports\Templates\Product\ProductMaatExporter::class,
        ],
    ],
    'company' => [
        'templates' => [
            \App\Services\MaatExports\Templates\Company\CompanyProductsExporter::class,
        ],
    ],
];
