<?php

// app/Models/Product.php
namespace App\Models;

use App\Models\Company;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperProduct
 */
class Product extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'datasets_products';


    protected $fillable = ['code', 'title', 'type', 'gamme_id', 'unit_price'];

    protected $casts = [
        'type' => ProductType::class,
    ];



    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'datasets_company_product')
            ->withPivot('unit_price')
            ->withTimestamps();
    }

    public function gamme(): BelongsTo
    {
        return $this->belongsTo(Gamme::class, 'gamme_id');
    }
}
