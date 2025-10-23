<?php

namespace App\Models\States\Quote;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion;

abstract class QuoteState extends State implements HasFilamentStateFusion
{
    use StateFusionInfo;

    public $isSaveHidden = false;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Validated::class, ToValidated::class)
            ->allowTransition(Draft::class, Canceled::class, ToCanceled::class)
            ->allowTransition(Canceled::class, Draft::class, CanceledToDraft::class)
            ->allowTransition(Validated::class, Draft::class, ToDraft::class);
    }
}
