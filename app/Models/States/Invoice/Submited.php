<?php 

namespace App\Models\States\Invoice;

class Submited extends InvoiceState
{
    public static $name = 'submited';

    public function color(): string
    {
        return 'success';
    }

}