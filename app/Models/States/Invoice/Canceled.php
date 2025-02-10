<?php

namespace App\Models\States\Invoice;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

class Canceled extends InvoiceState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'canceled';
    public $isSaveHidden = false;

    public function getLabel(): string
    {
        return __('Abandonné');
    }

    public function getColor(): array
    {
        return Color::Red;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-x-mark';
    }

    public function getDescription(): ?string
    {
        return 'Abandonné';
    }
}
