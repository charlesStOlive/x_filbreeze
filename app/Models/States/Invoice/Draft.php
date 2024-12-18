<?php 

namespace App\Models\States\Invoice;

class Draft extends InvoiceState
{
    public static $name = 'draft';

    public function color(): string
    {
        return 'gray';
    }

}