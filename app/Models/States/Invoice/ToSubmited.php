<?php

namespace App\Models\States\Invoice;

use Closure;
use DateTime;
use Filament\Forms;
use App\Models\Invoice;
use Filament\Support\Colors\Color;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
use App\Filament\ModelStates\Concerns\ProvidesSpatieTransitionToFilament;
use Filament\Support\Contracts\HasIcon;

class ToSubmited extends Transition implements FilamentSpatieTransition ,HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    private Invoice $invoice;
    private DateTime $submited_at;

    public function __construct(Invoice $invoice, DateTime $submited_at = null)
    {
        $this->invoice = $invoice;
        $this->submited_at  = $submited_at ? $submited_at : now();
    }

    public function getLabel(): string
    {
        return __('Soumettre');
    }

    public function getColor(): array
    {
        return Color::Green;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-paper-airplane';
    }


    public function handle(): Invoice
    {
        $this->invoice->state = new Submited($this->invoice);
        $this->invoice->submited_at = $this->submited_at;
        $this->invoice->save();
        return $this->invoice;
    }

    public static function fill($model, $formData): self
    {
        return new self(
            invoice: $model,
            submited_at: now(),
        );
    }

    public function form(): array | Closure | null
    {
        return [
            Forms\Components\DateTimePicker::make('submited_at')
                ->label('Validé le')
                ->default(now())
                ->helperText(__('Vous devez saisir une date de soumission.'))
        ];
    }
}
