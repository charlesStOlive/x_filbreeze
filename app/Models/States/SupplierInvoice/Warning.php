<?php

namespace App\Models\States\SupplierInvoice;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

class Warning extends SupplierInvoiceState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'warning';
    public $isSaveHidden = false;

    public function getLabel(): string
    {
        return __('Avertissement');
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public function getDescription(): ?string
    {
        return __('Avertissement - vérification recommandée');
    }
}
