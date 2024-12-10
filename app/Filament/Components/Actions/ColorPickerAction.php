<?php

namespace App\Filament\Components\Actions;

use ColorThief\ColorThief;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Storage;

class ColorPickerAction extends Action
{
    public $colorPalettes = [];

    public function onMount($record): void
    {
        $imagePath = $record->getFirstMediaPath('logo');

        if (!$imagePath) {
            $this->colorPalettes = []; // Définit une palette vide si aucune image n'est trouvée
            return;
        }

        // Générer la palette de couleurs
        $palette = ColorThief::getPalette($imagePath, 10);

        // Convertir en hexadécimal
        $this->colorPalettes = array_map(function ($color) {
            return sprintf('#%02x%02x%02x', ...$color);
        }, $palette);
        \Log::info($this->colorPalettes);
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
