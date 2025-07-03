<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gamme extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'datasets_gammes';

    protected $fillable = ['id', 'name', 'slug'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'gamme_id');
    }
}
