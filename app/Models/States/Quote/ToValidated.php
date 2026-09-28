<?php

namespace App\Models\States\Quote;

use Filament\Forms\Components\DateTimePicker;
use Closure;
use Filament\Forms;
use App\Models\Quote;
use App\Support\BusinessDate;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;
use Filament\Support\Contracts\HasIcon;

class ToValidated extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;
    public function __construct(
        private Quote $quote,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Valider devis');
    }
 
    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-check';
    }

     public function handle(): Quote
    {
        $this->quote->state = new Validated($this->quote);
        $this->quote->validated_at = $this->data['validated_at'];
        $this->quote->save();
        return $this->quote;
    }

    public static function fill($model, $formData): self
    {
        return new self(
            quote: $model,
            data: $formData,
        );
    }

    public function form(): array | Closure | null
    {
        return [
            BusinessDate::picker(DateTimePicker::make('validated_at'))
                ->label('Validé le')
                ->helperText(__('Date de validation'))
        ];
    }

}