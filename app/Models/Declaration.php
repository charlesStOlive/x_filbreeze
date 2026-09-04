<?php

namespace App\Models;

use App\Services\Declarations\DeclarationCalculator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Declaration extends Model
{
    public const TYPE_VAT = 'vat';

    public const TYPE_URSSAF = 'urssaf';

    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

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

    public static function modeOptions(): array
    {
        return [
            self::MODE_AUTOMATIC => 'Automatique',
            self::MODE_MANUAL => 'Manuel',
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
        return Attribute::make(
            get: fn (): float => $this->turnover_excluding_tax_cents / 100,
            set: fn (mixed $value): array => ['turnover_excluding_tax_cents' => $this->toCents($value)],
        );
    }

    protected function vatCollected(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->vat_collected_cents / 100,
            set: fn (mixed $value): array => ['vat_collected_cents' => $this->toCents($value)],
        );
    }

    protected function vatDeductible(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->vat_deductible_cents / 100,
            set: fn (mixed $value): array => ['vat_deductible_cents' => $this->toCents($value)],
        );
    }

    protected function vatDue(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->type === self::TYPE_VAT
                ? app(DeclarationCalculator::class)->vatBalanceCents(
                    (int) $this->vat_collected_cents,
                    (int) $this->vat_deductible_cents,
                    $this->previousVatCreditCents(),
                )['due'] / 100
                : $this->vat_due_cents / 100,
            set: fn (mixed $value): array => ['vat_due_cents' => $this->toCents($value)],
        );
    }

    protected function vatCredit(): Attribute
    {
        return Attribute::get(fn (): float => max(
            0,
            $this->vat_deductible_cents + $this->previousVatCreditCents() - $this->vat_collected_cents,
        ) / 100);
    }

    protected function previousVatCredit(): Attribute
    {
        return Attribute::get(fn (): float => $this->previousVatCreditCents() / 100);
    }

    private function previousVatCreditCents(): int
    {
        if ($this->type !== self::TYPE_VAT || ! $this->period_start) {
            return 0;
        }

        return app(DeclarationCalculator::class)->previousVatCreditCents(
            $this->period_start,
            $this->exists ? (int) $this->getKey() : null,
        );
    }

    private function toCents(mixed $value): int
    {
        return (int) round(((float) ($value ?? 0)) * 100);
    }
}
