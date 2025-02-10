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

class ToCanceled extends Transition implements FilamentSpatieTransition ,HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;
    private Quote $quote;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
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

     public function handle(): Quote
    {
        $this->quote->state = new Canceled($this->quote);
        $this->quote->validated_at = null;
        $this->quote->save();
        return $this->quote;
    }

}