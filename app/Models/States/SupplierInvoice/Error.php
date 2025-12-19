<?php

namespace App\Models\States\SupplierInvoice;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

class Error extends SupplierInvoiceState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'error';
    public $isSaveHidden = false;

    public function getLabel(): string
    {
        return __('Erreur');
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-exclamation-circle';
    }

    public function getDescription(): ?string
    {
        return __('Erreur - nécessite correction');
    }
}
