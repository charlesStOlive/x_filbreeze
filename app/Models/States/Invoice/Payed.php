<?php 

namespace App\Models\States\Invoice;

class Payed extends InvoiceState
{
    public static $name = 'paid';

    public function color(): string
    {
        return 'primary';
    }

}