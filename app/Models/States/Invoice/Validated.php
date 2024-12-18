<?php 

namespace App\Models\States\Invoice;

class Validated extends InvoiceState
{
    public static $name = 'validated';

    public function color(): string
    {
        return 'primary';
    }

}