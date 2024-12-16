<?php

namespace App\Filament\Utils;


use Filament\Actions\Action;
use App\Forms\Components\Diff2Html;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use ValentinMorice\FilamentJsonColumn\FilamentJsonColumn;
use Filament\Forms\Components\Actions\Action as FormAction;


class IaUtils
{
    /**
     * Crée une action pour corriger les textes via Mistral IA.
     *
     * @param  string  $resource  La classe de la ressource utilisée
     * @return Action
     */
    public static function MisrtalCorrectionAction(string $resource): Action
    {
        return Action::make('Orthographes')
            ->icon('fas-wand-sparkles')
            ->fillForm(function ($record) {
                $texts = $record->extractTextToJson();
                $corrected = static::callMistralAgent(json_encode($texts));
                return [
                    'data_for_ia' => $texts,
                    'data_corrected' => $corrected,
                ];
            })
            ->form([
                FilamentJsonColumn::make('data_for_ia'),
                FilamentJsonColumn::make('data_corrected'),
                Diff2Html::make('jsonComparison')
                    ->version1(fn($get) => $get('data_for_ia'))
                    ->version2(fn($get) => json_decode($get('data_corrected'), true)),
            ])
            ->action(function ($record, $livewire, $data) use ($resource) {
                $record->injectTextFromJson(json_decode($data['data_corrected'], true));
                $record->save();
                return redirect()->to($resource::getUrl('edit', ['record' => $record]));
            })
            ->modalWidth('7xl');
    }

    public static function callMistralAgent(string $mistralPrompt): string
    {
        $mistralAgent = new \App\Services\Ia\MistralAgentService(); // Instanciation directe
        $agentId = 'ag:3e2c948d:20241213:correction-ortographe:b3c27f0b';
        $response = $mistralAgent->callAgent($agentId, $mistralPrompt);
        return $response['choices'][0]['message']['content'] ?? '';
    }

    

    
}
