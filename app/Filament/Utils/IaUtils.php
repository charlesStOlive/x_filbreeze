<?php

namespace App\Filament\Utils;


use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use ValentinMorice\FilamentJsonColumn\FilamentJsonColumn;


class IaUtils
{
    public static function MisrtalCorrectionAction(): Action
    {
        return Action::make('Corriger le texte')
            ->icon('fas-wand-sparkles')
            ->fillForm(function ($record)   {
                // Obtenir le chemin du CSS généré par Vite
                $updatedData = parent::getState();
                $record->fill($updatedData);
                $dataToSend = $record->extractTextToJson();

                return [
                    'data_for_ia' => $dataToSend,
                ];
            })
            ->form([
                FilamentJsonColumn::make('data_for_ia'),
            ])
            ->action(function (array $data)  {
               \Log::info($data);
            });
    }

    public static function MisrtalCorrectionFormAction(): FormAction
    {
        return FormAction::make('Corriger le texte')
            ->icon('fas-wand-sparkles')
            ->fillForm(function ($record, $component)   {
                // Obtenir le chemin du CSS généré par Vite
                $updatedData = $component->getState();
                $record->fill($updatedData);
                $dataToSend = $record->extractTextToJson();

                return [
                    'data_for_ia' => $dataToSend,
                ];
            })
            ->form([
                FilamentJsonColumn::make('data_for_ia'),
            ])
            ->action(function (array $data)  {
               \Log::info($data);
            });
    }

    
}
