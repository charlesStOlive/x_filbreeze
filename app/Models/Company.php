<?php

namespace App\Models;

use App\Enums\Country;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\Quote;
use App\Enums\CompanyType;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCompany
 */
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
        'qonto_raw' => 'array',
        'qonto_client_synced_at' => 'datetime',
        'qonto_e_invoicing_reachable' => 'boolean',
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'company_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'company_id');
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
        return Attribute::get(fn() => $this->country?->label());
    }

    public function qontoExportStatus(): Attribute
    {
        return Attribute::get(function (): string {
            if (filled($this->qonto_client_id)) {
                return 'referenced';
            }

            return $this->isReadyForQontoExport() ? 'ready' : 'missing';
        });
    }

    public function qontoExportStatusLabel(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->qonto_export_status) {
            'referenced' => 'Référencé Qonto',
            'ready' => 'Prêt Qonto',
            default => 'À compléter',
        });
    }

    public function qontoExportStatusIcon(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->qonto_export_status) {
            'referenced' => 'heroicon-o-check-badge',
            'ready' => 'heroicon-o-cloud-arrow-up',
            default => 'heroicon-o-exclamation-triangle',
        });
    }

    public function qontoExportStatusColor(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->qonto_export_status) {
            'referenced' => 'success',
            'ready' => 'info',
            default => 'warning',
        });
    }

    public function qontoExportStatusDescription(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->qonto_export_status !== 'missing') {
                return $this->qonto_export_status_label;
            }

            return 'À compléter : ' . implode(', ', $this->missingQontoExportFields());
        });
    }

    public function isReadyForQontoExport(): bool
    {
        return $this->missingQontoExportFields() === [];
    }

    public function missingQontoExportFields(): array
    {
        $missing = [];

        if (! filled($this->title)) {
            $missing[] = 'nom';
        }

        if (! filled($this->address)) {
            $missing[] = 'adresse';
        }

        if (! filled($this->city)) {
            $missing[] = 'ville';
        }

        if (! filled($this->cp)) {
            $missing[] = 'code postal';
        }

        if (! filled($this->tax_identification_number) && ! filled($this->siret) && ! filled($this->vat_number)) {
            $missing[] = 'SIREN/SIRET ou TVA';
        }

        return $missing;
    }
}
