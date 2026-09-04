<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Declaration extends Model
{
    public const TYPE_VAT = 'vat';

    public const TYPE_URSSAF = 'urssaf';

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'covered_months' => 'array',
        'calculation_details' => 'array',
        'filed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_VAT => 'TVA',
            self::TYPE_URSSAF => 'URSSAF',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'À déclarer',
            'filed' => 'Déclarée',
            'paid' => 'Payée',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function turnoverExcludingTax(): Attribute
    {
        return Attribute::get(fn (): float => $this->turnover_excluding_tax_cents / 100);
    }

    protected function vatCollected(): Attribute
    {
        return Attribute::get(fn (): float => $this->vat_collected_cents / 100);
    }

    protected function vatDeductible(): Attribute
    {
        return Attribute::get(fn (): float => $this->vat_deductible_cents / 100);
    }

    protected function vatDue(): Attribute
    {
        return Attribute::get(fn (): float => $this->vat_due_cents / 100);
    }
}
