<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\Page;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use App\Models\Quote; // Assurez-vous que le modèle est correctement importé

class PreviewPdf extends Page
{
    protected static string $resource = QuoteResource::class;

    protected static string $view = 'components.html_preveiw_page'; // Vue associée

    public $invoice;

    public function mount($record)
    {
        $this->invoice = Quote::findOrFail($record); // Récupère le record par ID
    }

    protected function getViewData(): array
    {
        $htmlContent = view('pdf.quote.main', [
            'record' => $this->invoice,
            'preview' => true,
             'avoid_full_break' => false,
            'avoid_amount_break' => true,
            'avoid_row_break' => true,
        ])->render();

        return [
            'htmlContent' => $htmlContent, // Contenu HTML généré
            'quote' => $this->invoice,
            'preview' => true,
        ];
    }
}
