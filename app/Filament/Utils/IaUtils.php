<?php

namespace App\Filament\Utils;

use ColorThief\ColorThief;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions\Action;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Services\Helpers\ViteHelper;

class ImageUtils
{
    public static function getMistralCorrection(string $source, string $fieldName): Action
    {
        return Action::make('Corriger le texte')
            ->icon('heroicon-o-photo')
            ->mountUsing(function ($livewire, $record, $get) use ($source) {
                $finalPath = null;
                $temporaryFile =  $get($source);
                \Log::info($temporaryFile);
                $uploadedFile = reset($temporaryFile);

                if ($uploadedFile instanceof TemporaryUploadedFile) {
                    $finalPath = $uploadedFile->getRealPath() ?? null;
                } else if ($record->getFirstMedia('logo') ?? null) {
                    $finalPath = $record->getFirstMedia('logo')->getPath();
                } else {
                    $livewire->colorPalettes = [];
                    return;
                }

                $palette = ColorThief::getPalette($finalPath, 4);

                \Log::info($palette);

                // Convertir en hexadécimal
                $colorPalettes = array_map(function ($color) {
                    return sprintf('#%02x%02x%02x', ...$color);
                }, $palette);
                // Mettre à jour les options dynamiquement dans Livewire
                $livewire->colorPalettes = $colorPalettes;
            })
            ->form([
                Select::make('select-color')
                    ->label('Palette de couleurs')
                    ->view('filament.forms.components.color-palette')
                    ->options(fn($livewire) => $livewire->colorPalettes ?? []) // Récupère les options depuis Livewire
                    ->reactive(), // Rend le champ réactif
            ])
            ->action(function (array $data, callable $set) use ($fieldName) {
                // Mettre à jour le champ avec la couleur sélectionnée
                $set($fieldName, $data['select-color']);
            });
    }

    
}
