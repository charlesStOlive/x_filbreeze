<?php

namespace App\Models\States\Invoice;

use Closure;
use Filament\Forms;
use App\Models\Invoice;
use App\Services\Qonto\CrmInvoiceQontoService;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
// use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
// use App\Filament\ModelStates\Concerns\ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;
use Filament\Support\Contracts\HasIcon;

class ToCanceled extends Transition implements FilamentSpatieTransition ,HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;
    public function __construct(
        private Invoice $invoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Abandonner');
    }
 
    public function getColor(): string
    {
        return 'red';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-x-mark';
    }

     public function handle(): Invoice
    {
        app(CrmInvoiceQontoService::class)->cancel($this->invoice);

        $this->invoice->state = new Canceled($this->invoice);
        $this->invoice->save();

        return $this->invoice;
    }

}