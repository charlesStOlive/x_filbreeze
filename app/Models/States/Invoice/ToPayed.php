<?php

namespace App\Models\States\Invoice;

use Closure;
use DateTime;
use Filament\Forms;
use App\Models\Invoice;
use Spatie\ModelStates\Transition;
use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
use App\Filament\ModelStates\Concerns\ProvidesSpatieTransitionToFilament;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

class ToPayed extends Transition implements FilamentSpatieTransition, HasIcon, HasColor, HasLabel
{
    use ProvidesSpatieTransitionToFilament;

    private Invoice $invoice;
    private DateTime $payed_at;

    public function __construct(Invoice $invoice, DateTime $payed_at = null)
    {
        $this->invoice = $invoice;
        $this->payed_at  = $payed_at ? $payed_at : now();
    }

    public function getLabel(): string
    {
        return __('Payement reçus');
    }
 
    public function getColor(): array
    {
        return Color::Green;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-check';
    }

    public function handle(): Invoice
    {
        $this->invoice->state = new Payed($this->invoice);
        $this->invoice->payed_at = $this->payed_at;
        $this->invoice->save();
        return $this->invoice;
    }

    public static function fill($model, $formData): self
    {
        return new self(
            invoice: $model,
            payed_at: now(),
        );
    }

    public function form(): array | Closure | null
    {
        return [
            Forms\Components\DateTimePicker::make('payed_at')
                ->label('Payé le')
                ->default(now())
                ->helperText(__('Vous devez saisir une date de paiement.'))
        ];
    }
}
