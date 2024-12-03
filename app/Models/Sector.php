<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\VendorOverrides\ModelTree;

class Sector extends Model
{
    use HasFactory, ModelTree;
    use InteractsWithMedia;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_sectors';

    /**
     * Get the companies for the sector.
     */
    public function companies()
    {
        return $this->hasMany(Company::class, 'sector_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();
    }

    
}
