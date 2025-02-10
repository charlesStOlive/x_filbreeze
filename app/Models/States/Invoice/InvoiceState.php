<?php 

namespace App\Models\States\Invoice;


use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use App\Filament\ModelStates\Contracts\FilamentSpatieState;
use App\Filament\ModelStates\Concerns\ProvidesSpatieStateToFilament;

abstract class InvoiceState extends State implements FilamentSpatieState
{
    use ProvidesSpatieStateToFilament;

    public $isSaveHidden = false;
    
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Submited::class, ToSubmited::class)
            ->allowTransition(Submited::class, Payed::class, ToPayed::class)
            ->allowTransition(Draft::class, Canceled::class, ToCanceled::class)
            
        ;
    }
}