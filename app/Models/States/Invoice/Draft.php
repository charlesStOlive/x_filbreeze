<?php 

namespace App\Models\States\Invoice;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
class Draft extends InvoiceState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'draft';

    public function getLabel(): string
    {
        return __('Brouillon');
    }
 
    public function getColor(): array
    {
        return Color::Gray;
    }
 
    public function getIcon(): string
    {
        return 'heroicon-o-pencil';
    }
 
    public function getDescription(): ?string
    {
        return 'Brouillon.';
    }

}