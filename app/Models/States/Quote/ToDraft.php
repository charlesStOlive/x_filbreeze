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

class ToDraft extends Transition implements FilamentSpatieTransition ,HasColor, HasLabel
{
    use ProvidesSpatieTransitionToFilament;
    private Quote $quote;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
    }

    public function getLabel(): string
    {
        return __('Annuler la validation');
    }
 
    public function getColor(): array
    {
        return Color::Red;
    }

     public function handle(): Quote
    {
        $this->quote->state = new Draft($this->quote);
        $this->quote->validated_at = null;
        $this->quote->save();
        return $this->quote;
    }

}