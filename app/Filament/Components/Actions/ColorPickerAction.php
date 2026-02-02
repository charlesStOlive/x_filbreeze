<?php

namespace App\Filament\Components\Actions;

use Filament\Actions\Action;
use ColorThief\ColorThief;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\Facades\Storage;

class ColorPickerAction extends Action
{
    public $colorPalettes = [];

    public function onMount($record): void
    {
        if (!$record) {
            $this->colorPalettes = [];
            return;
        }

        $imagePath = $record->getFirstMediaPath('logo');

        if (!$imagePath) {
            $this->colorPalettes = []; // Définit une palette vide si aucune image n'est trouvée
            return;
        }

        // Générer la palette de couleurs
        try {
            $palette = ColorThief::getPalette($imagePath, 10);
        } catch (\Throwable $e) {
            $this->colorPalettes = [];
            return;
        }

        // Convertir en hexadécimal
        $this->colorPalettes = array_map(function ($color) {
            return sprintf('#%02x%02x%02x', ...$color);
        }, $palette);
    }

    public function getFormSchema(): array
    {
        return [
            ViewField::make('color-picker')
                ->view('filament.forms.components.color-palette', ['colorPalettes' => $this->colorPalettes]),
        ];
    }

    public function handle(array $data): void
    {
        $this->emit('updatePrimaryColor', $data['selected_color']);
    }
}
