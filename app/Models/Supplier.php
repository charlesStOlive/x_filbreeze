<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'address', 'city', 'country'];

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    

    
}
