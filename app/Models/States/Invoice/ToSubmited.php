<?php

namespace App\Models\States\Invoice;

use Filament\Forms\Components\DateTimePicker;
use Closure;
use DateTime;
use Filament\Forms;
use App\Models\Invoice;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
// use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
// use App\Filament\ModelStates\Concerns\ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;
use Filament\Support\Contracts\HasIcon;

class ToSubmited extends Transition implements FilamentSpatieTransition ,HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private Invoice $invoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Soumettre');
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-paper-airplane';
    }


    public function handle(): Invoice
    {
        $this->invoice->state = new Submited($this->invoice);
        $this->invoice->submited_at = $this->data['submited_at'];
        $this->invoice->save();
        return $this->invoice;
    }

    public static function fill($model, $formData): self
    {
        return new self(
            invoice: $model,
            data: $formData,
        );
    }

    public function form(): array | Closure | null
    {
        return [
            DateTimePicker::make('submited_at')
                ->label('Validé le')
                ->default(now())
                ->helperText(__('Vous devez saisir une date de soumission.'))
        ];
    }
}
