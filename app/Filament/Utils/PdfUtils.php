<?php

namespace App\Filament\Utils;

use Filament\Actions\Action;
use App\Services\Helpers\ViteHelper;
use Filament\Forms;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\View;

class PdfUtils
{
    public static function CreateActionPdf(string $label, string $pdv_view_path): Action
    {
        return Action::make('generate_invoice')
            ->label('Génerer '. $label)
            ->form([
                // Champs de configuration du PDF
                Forms\Components\Checkbox::make('avoid_full_break')
                    ->label('Empêcher les sauts de page au milieu du tableau principal')
                    ->default(false),
                Forms\Components\Checkbox::make('avoid_amount_break')
                    ->label('Empêcher les sauts de page au milieu des montants')
                    ->default(true),
                Forms\Components\Checkbox::make('avoid_row_break')
                    ->label('Empêcher les sauts au milieu d\'une ligne')
                    ->default(true),
                \Filament\Forms\Components\ViewField::make('html_preview')
                    ->view('components.html_preview') // Vue personnalisée pour afficher l'aperçu
                    ->label("Aperçu"),
            ])
            ->fillForm(function ($record) use ($pdv_view_path) {
                // Obtenir le chemin du CSS généré par Vite
                $cssPath = ViteHelper::viteAsset('resources/css/pdf/theme.css');
                // Générer le contenu HTML à partir de la vue
                $htmlContent = View::make($pdv_view_path, [
                    'record' => $record,
                    'cssPath' => $cssPath,
                    'avoid_full_break' => false,
                    'avoid_amount_break' => true,
                    'avoid_row_break' => true,
                ])->render();

                return [
                    'html_preview' => $htmlContent,
                    'avoid_full_break' => false,
                    'avoid_amount_break' => true,
                    'avoid_row_break' => true,
                ];
            })
            ->modalHeading('Aperçu et téléchargement')
            ->action(function ($record, $data) use ($pdv_view_path) {
                // Génération du PDF
                $cssPath = ViteHelper::viteAsset('resources/css/pdf/theme.css');
                $htmlContent = View::make($pdv_view_path, [
                    'record' => $record,
                    'cssPath' => $cssPath,
                    'avoid_full_break' => $data['avoid_full_break'] ?? false,
                    'avoid_amount_break' => $data['avoid_amount_break'] ?? false,
                    'avoid_row_break' => $data['avoid_row_break']?? false,
                ])->render();

                $fileName = "{$record->code}.pdf";
                $filePath = storage_path('app/public/' . $fileName);

                Browsershot::html($htmlContent)
                    ->format('A4')
                    ->scale(0.75)
                    ->margins(25, 25, 25, 25, 'px')
                    ->emulateMedia('screen')
                    ->showBackground()
                    ->savePdf($filePath);

                // Télécharger le fichier
                return response()->download($filePath)->deleteFileAfterSend(true);
            })
            ->modalWidth('7xl');
    }
}
