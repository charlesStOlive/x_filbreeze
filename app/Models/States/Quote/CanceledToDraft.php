<?php

namespace App\Models\States\Quote;

use App\Models\Quote;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;

class CanceledToDraft extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;
    
    public function __construct(
        private Quote $quote,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Revenir au brouillon');
    }
 
    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-arrow-uturn-left';
    }

    public function handle(): Quote
    {
        $this->quote->state = new Draft($this->quote);
        $this->quote->validated_at = null;
        $this->quote->save();
        return $this->quote;
    }
}