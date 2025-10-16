<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\Page;
use App\Services\Helpers\ViteHelper;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use App\Models\Quote;

class PreviewPdf extends Page
{
    protected static string $resource = QuoteResource::class;

    protected string $view = 'components.html_preveiw_page'; // Vue associée

    public $quote;
    public $template;

    public function mount($record, $template = null)
    {
        $this->quote = Quote::findOrFail($record);
        $this->template = $template ?: 'base'; // Template par défaut
    }

    protected function getViewData(): array
    {
        // Construire le chemin du template
        $templatePath = "pdf.quote.{$this->template}";
        
        // Vérifier si le template existe, sinon utiliser le template par défaut
        if (!view()->exists($templatePath)) {
            $templatePath = 'pdf.quote.base';
            $this->template = 'base';
        }

        // Utiliser ViteHelper avec le mode preview (hot reload si serveur de dev actif)
        $cssPath = ViteHelper::viteAsset('resources/css/pdf/pdf.css', false);

        $htmlContent = view($templatePath, [
            'quote' => $this->quote,
            'preview' => true,
            'cssPath' => $cssPath,
            'options' => [
                'avoid_break' => false,
                'avoid_amount_break' => true,
                'avoid_row_break' => true,
            ],
        ])->render();

        return [
            'htmlContent' => $htmlContent, // Contenu HTML généré
            'quote' => $this->quote,
            'template' => $this->template,
            'preview' => true,
        ];
    }

    public function getTitle(): string
    {
        return "Preview PDF - {$this->quote->code} ({$this->template})";
    }
}
