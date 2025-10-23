<?php

namespace App\Models\States\SupplierInvoice;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion;

abstract class SupplierInvoiceState extends State implements HasFilamentStateFusion
{
    use StateFusionInfo;

    public $isSaveHidden = false;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Validated::class, DraftToValidated::class)
            ->allowTransition(Draft::class, Canceled::class, DraftToCanceled::class)
            ->allowTransition(Validated::class, Canceled::class, ValidatedToCanceled::class)
            ->allowTransition(Canceled::class, Draft::class, CanceledToDraft::class)
            ->allowTransition(Draft::class, ToDraft::class)
            ->allowTransition(Validated::class, ToValidated::class)
            ->allowTransition(Canceled::class, ToCanceled::class);
    }
}
