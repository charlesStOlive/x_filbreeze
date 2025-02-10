<?php

namespace App\Models\States\Invoice;

use Closure;
use Filament\Forms;
use App\Models\Invoice;
use Filament\Support\Colors\Color;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
use App\Filament\ModelStates\Concerns\ProvidesSpatieTransitionToFilament;
use Filament\Support\Contracts\HasIcon;

class ToCanceled extends Transition implements FilamentSpatieTransition ,HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;
    private Invoice $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function getLabel(): string
    {
        return __('Abandonner');
    }
 
    public function getColor(): array
    {
        return Color::Red;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-x-mark';
    }

     public function handle(): Invoice
    {
        $this->invoice->state = new Canceled($this->invoice);
        $this->invoice->save();
        return $this->invoice;
    }

}