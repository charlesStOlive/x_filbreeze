<?php

namespace App\Models\States\SupplierInvoice;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

class Canceled extends SupplierInvoiceState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'canceled';
    public $isSaveHidden = false;

    public function getLabel(): string
    {
        return __('Annulé');
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-pencil';
    }

    public function getDescription(): ?string
    {
        return __('Annulé');
    }
}
