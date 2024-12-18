<?php 

namespace App\Models\States\Invoice;

class Canceled extends InvoiceState
{
    public static $name = 'canceled';

    public function color(): string
    {
        return 'gray';
    }

}