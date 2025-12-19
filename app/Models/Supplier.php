<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * @mixin IdeHelperSupplier
 */
class Supplier extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_suppliers';


    protected $guarded = ['id'];

    protected $casts = [
        'alternative_names' => 'array',
    ];

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class, 'supplier_id', 'id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
