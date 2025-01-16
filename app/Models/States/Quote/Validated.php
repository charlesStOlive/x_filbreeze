<?php

namespace App\Models\States\Quote;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

class Validated extends QuoteState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'validated';
    public $isSaveHidden = true;

    public function getLabel(): string
    {
        return __('Validé');
    }

    public function getColor(): array
    {
        return Color::Green;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-check';
    }

    public function getDescription(): ?string
    {
        return 'Devis validé';
    }
}
