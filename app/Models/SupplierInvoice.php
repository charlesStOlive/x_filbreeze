<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierInvoice extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = ['supplier_id', 'invoice_number', 'invoice_date', 'total_amount', 'status', 'notes'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    
}
