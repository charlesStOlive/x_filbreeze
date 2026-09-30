<?php

namespace App\Models\States\Quote;

use Closure;
use Filament\Forms;
use App\Models\Quote;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;

class ToDraft extends Transition implements FilamentSpatieTransition ,HasColor, HasLabel, HasIcon
{
    /** Clé de sa permission : `{liste}.transition.annuler-validation` (filament-permission-manager) ; seules les transitions faites par une personne en ont une. */
    public static string $key = 'annuler-validation';

    use ProvidesSpatieTransitionToFilament;
    public function __construct(
        private Quote $quote,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Annuler validation');
    }
 
    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-x-mark';
    }

     public function handle(): Quote
    {
        $this->quote->state = new Draft($this->quote);
        $this->quote->validated_at = null;
        $this->quote->save();
        return $this->quote;
    }

}