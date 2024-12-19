<?php 

namespace App\Models\States\Invoice;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
class Payed extends InvoiceState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'payed';
    public $isSaveHidden = true;

    public function getLabel(): string
    {
        return __('Payé');
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
        return 'Enregistrement du paiement.';
    }

}