<?php

namespace App\Models\States\Quote;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use App\Filament\ModelStates\Contracts\FilamentSpatieState;
use App\Filament\ModelStates\Concerns\ProvidesSpatieStateToFilament;

abstract class QuoteState extends State implements FilamentSpatieState
{
    use ProvidesSpatieStateToFilament;

    public $isSaveHidden = false;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Validated::class, ToValidated::class)
            ->allowTransition(Draft::class, Canceled::class, ToCanceled::class)
            ->allowTransition(Validated::class, Draft::class, ToDraft::class);
    }
}
