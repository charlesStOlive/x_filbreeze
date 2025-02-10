<?php

namespace App\Models\States\Quote;

use Closure;
use Filament\Forms;
use App\Models\Quote;
use Filament\Support\Colors\Color;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
use App\Filament\ModelStates\Concerns\ProvidesSpatieTransitionToFilament;
use Filament\Support\Contracts\HasIcon;

class ToValidated extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;
    private Quote $quote;
    private $validated_at;

    public function __construct(Quote $quote, $validated_at = null)
    {
        $this->quote = $quote;
        $this->validated_at  = $validated_at ? $validated_at : null;
    }

    public function getLabel(): string
    {
        return __('Valider devis');
    }
 
    public function getColor(): array
    {
        return Color::Green;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-check';
    }

     public function handle(): Quote
    {
        $this->quote->state = new Validated($this->quote);
        $this->quote->validated_at = $this->validated_at;
        $this->quote->save();
        return $this->quote;
    }

    public static function fill($model, $formData): self
    {
        return new self(
            quote: $model,
            validated_at: now(),
        );
    }

    public function form(): array | Closure | null
    {
        return [
            Forms\Components\DateTimePicker::make('validated_at')
                ->label('Validé le')
                ->default(now())
                ->helperText(__('Date de validation'))
        ];
    }

}