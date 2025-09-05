<?php

namespace App\Filament\Utils;

use Log;
use Filament\Actions\Action;
use App\Forms\Components\Diff2Html;
use Filament\Forms\Components\Hidden;
use App\Services\Ia\IaService;
use App\Exceptions\MistralException;

class IaUtils
{
    /**
     * Crée une action pour corriger les textes via Mistral IA.
     *
     * @param  string  $resource  La classe de la ressource utilisée
     * @param  bool  $hidden  Si l'action doit être cachée
     * @return Action
     */
    public static function MistralCorrectionAction(string $resource, bool $hidden = false): Action
    {
        return Action::make('Orthographes')
            ->icon('fas-wand-sparkles')
            ->fillForm(function ($record) {
                $texts = $record->extractTextToJson();
                $corrected = static::correctTexts(json_encode($texts));

                return [
                    'data_for_ia' => $texts,
                    'data_corrected' => $corrected,
                ];
            })
            ->schema([
                Hidden::make('data_for_ia'),
                Hidden::make('data_corrected'),
                Diff2Html::make('jsonComparison')
                    ->version1(fn($get) => $get('data_for_ia'))
                    ->version2(fn($get) => json_decode($get('data_corrected'), true)),
            ])
            ->action(function ($record, $livewire, $data) use ($resource) {
                $record->injectTextFromJson(json_decode($data['data_corrected'], true));
                $record->save();
                return redirect()->to($resource::getUrl('edit', ['record' => $record]));
            })
            ->hidden($hidden)
            ->color('gray')
            ->modalWidth('7xl');
    }

    /**
     * Corrige les textes via le service IA
     *
     * @param string $jsonText
     * @return string
     * @throws MistralException
     */
    protected static function correctTexts(string $jsonText): string
    {
        try {
            $iaService = app(IaService::class);
            return $iaService->correctText($jsonText);
        } catch (MistralException $e) {
            Log::error('Erreur lors de la correction de texte', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
