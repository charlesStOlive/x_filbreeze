<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\Page;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use App\Models\Invoice; // Assurez-vous que le modèle est correctement importé

class PreviewPdf extends Page
{
    protected static string $resource = InvoiceResource::class;

    protected static string $view = 'components.html_preveiw_page'; // Vue associée

    public $invoice;

    public function mount($record)
    {
        $this->invoice = Invoice::findOrFail($record); // Récupère le record par ID
    }

    protected function getViewData(): array
    {
        $htmlContent = view('pdf.invoice.main', [
            'record' => $this->invoice,
            'preview' => true,
            'avoid_full_break' => false,
            'avoid_amount_break' => true,
            'avoid_row_break' => true,
        ])->render();

        return [
            'htmlContent' => $htmlContent, // Contenu HTML généré
            'record' => $this->invoice,
            'preview' => true,
             'avoid_full_break' => false,
            'avoid_amount_break' => true,
            'avoid_row_break' => true,
        ];
    }
}
