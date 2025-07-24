<?php

namespace App\Models;

use App\Enums\Country;
use App\Models\Product;
use App\Enums\CompanyType;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_companies';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];



    protected $casts = [
        'others' => 'json',
        'type' => CompanyType::class,
        'country' => Country::class, 
    ];

    /**
     * Get the sector that owns the company.
     */
    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    /**
     * Get the contacts for the company.
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class, 'company_id');
    }


    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'datasets_company_product')
            ->withPivot('unit_price')
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();
    }

    public function logo_cloudinary()
    {
        return $this->morphOne(ImageCloudinary::class, 'model');
    }

    /**
     * GETTERS
     */
    public function countryName(): Attribute
    {
        return Attribute::get(fn () => $this->country?->label());
    }
}
