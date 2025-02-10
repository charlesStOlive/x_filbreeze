<?php 

namespace App\Models\States\Invoice;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
class Submited extends InvoiceState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'submited';

    public function getLabel(): string
    {
        return 'Soumise';
    }
 
    public function getColor(): array
    {
        return Color::Blue;
    }
 
    public function getIcon(): string
    {
        return 'heroicon-o-paper-airplane';
    }
 
    public function getDescription(): ?string
    {
        return 'Facture soumise.X';
    }

    public static function rules(): array
    {
        return [
            'total_ht' => ['required', 'numeric', 'min:1'],
        ];
    }

}