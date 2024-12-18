<?php 

namespace App\Models\States\Invoice;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class InvoiceState extends State
{
    abstract public function color(): string;
    
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Submited::class)
            ->allowTransition(Submited::class, Canceled::class)
            ->allowTransition(Submited::class, Payed::class)
        ;
    }
}