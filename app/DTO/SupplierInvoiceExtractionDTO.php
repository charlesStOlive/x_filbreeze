<?php

namespace App\DTO;

use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

use App\Support\Prism\HasPrismSchema;

class SupplierInvoiceExtractionDTO extends Data
{
    use HasPrismSchema;

    public function __construct(
        public string $supplier_name,
        public ?string $supplier_siret,
        // public ?string $supplier_vat,
        public ?string $invoice_number,

        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'])]
        public Carbon $invoice_at,

        public ?string $currency = 'EUR',

        public ?float $total_ht,
        public ?float $tx_tva,
        public ?float $tva,
        public ?float $total_ttc,

        public ?string $notes,
    ) {}


    protected static function prismFieldDescriptions(): array
    {
        return [
            'supplier_name'   => "Nom du fournisseur tel qu'affiché sur la facture",
            'supplier_siret'  => "SIRET du fournisseur (14 chiffres) si présent",
            'supplier_vat'    => "Numéro de TVA intracommunautaire (ex: FRxx...) si présent",

            'invoice_number'  => "Numéro de facture si présent",
            'invoice_at'      => "Date de facture (idéalement YYYY-MM-DD ; sinon d/m/Y etc.)",

            'currency'        => "Devise (ex: EUR)",

            'total_ht'        => "Total HT (montant numérique, sans symbole)",
            'tx_tva'          => "Taux de TVA (ex: 20 pour 20%)",
            'tva'             => "Montant TVA",
            'total_ttc'       => "Total TTC",

            'notes'           => "Notes: ambiguïtés, champs manquants, incertitudes, hypothèses",
        ];
    }

    protected static function prismRequiredOverrides(): array
    {
        // Très utile en extraction : on veut au moins identifier + dater.
        return ['supplier_name', 'invoice_at'];
    }
}
