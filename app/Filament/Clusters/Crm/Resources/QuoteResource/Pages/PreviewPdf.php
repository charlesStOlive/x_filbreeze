<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\Page;
use App\Services\Helpers\ViteHelper;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use App\Models\Quote; // Assurez-vous que le modèle est correctement importé

class PreviewPdf extends Page
{
    protected static string $resource = QuoteResource::class;

    protected string $view = 'components.html_preveiw_page'; // Vue associée

    public $quote;

    public function mount($record)
    {
        $this->quote = Quote::findOrFail($record); // Récupère le record par ID
    }

    protected function getViewData(): array
    {
        $htmlContent = view('pdf.quote.base', [
            'quote' => $this->quote,
            'preview' => true,
            'cssPath' => ViteHelper::viteAsset('resources/css/pdf/pdf.css'),
            'options' => [
                'avoid_break' => false,
                'avoid_amount_break' => true,
                'avoid_row_break' => true,
            ],
        ])->render();

        return [
            'htmlContent' => $htmlContent, // Contenu HTML généré
            'quote' => $this->quote,
            'preview' => true,
        ];
    }
}
