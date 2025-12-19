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
            ->allowTransition(Draft::class, Error::class, DraftToError::class)
            ->allowTransition(Draft::class, Warning::class, DraftToWarning::class)
            ->allowTransition(Error::class, Draft::class, ErrorToDraft::class)
            ->allowTransition(Warning::class, Validated::class, WarningToValidated::class)
            ->allowTransition(Validated::class, Draft::class, ValidatedToDraft::class);
    }
}
