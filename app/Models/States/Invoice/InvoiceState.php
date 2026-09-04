<?php 

namespace App\Models\States\Invoice;


use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
// use App\Filament\ModelStates\Contracts\FilamentSpatieState;
// use App\Filament\ModelStates\Concerns\ProvidesSpatieStateToFilament;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion;

abstract class InvoiceState extends State implements HasFilamentStateFusion
{
    use StateFusionInfo;

    public $isSaveHidden = false;
    
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Submited::class, ToSubmited::class)
            ->allowTransition(Submited::class, Payed::class, ToPayed::class)
            ->allowTransition(Draft::class, Canceled::class, ToCanceled::class)
            ->allowTransition(Submited::class, Canceled::class, ToCanceled::class)
            
        ;
    }
}