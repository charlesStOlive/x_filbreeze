<?php

namespace App\Models;

use App\Services\Declarations\DeclarationCalculator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Declaration extends Model
{
    public const TYPE_VAT = 'vat';

    public const TYPE_URSSAF = 'urssaf';

    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_QUARTERLY = 'quarterly';

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

    public static function periodOptions(): array
    {
        return [
            self::PERIOD_MONTHLY => 'Mensuel',
            self::PERIOD_QUARTERLY => 'Trimestriel',
        ];
    }

    /**
     * Cadence par défaut d'un type de déclaration, pilotée par config (utilisée par
     * les commandes automatiques). La création manuelle peut choisir une autre cadence.
     */
    public static function defaultPeriodFor(string $type): string
    {
        $default = $type === self::TYPE_URSSAF ? self::PERIOD_QUARTERLY : self::PERIOD_MONTHLY;

        $configured = config("declarations.periods.{$type}", $default);

        return array_key_exists($configured, self::periodOptions()) ? $configured : $default;
    }

    /**
     * Liste des périodes proposables pour un type et une cadence donnés, de la
     * première période gérée par l'app jusqu'à la prochaine à venir. Les périodes
     * qui ont déjà une déclaration (même démarrant à cette date avec une autre
     * cadence) sont exclues : ce sélecteur sert à générer une période manquante,
     * pas à en dupliquer une existante.
     *
     * @return array<string, string> date de début (Y-m-d) => libellé
     */
    public static function periodChoices(string $type, string $frequency): array
    {
        if (! array_key_exists($type, self::typeOptions()) || ! array_key_exists($frequency, self::periodOptions())) {
            return [];
        }

        $existingStarts = self::query()
            ->where('type', $type)
            ->get('period_start')
            ->map(fn (self $declaration): string => $declaration->period_start->toDateString())
            ->all();

        $months = $frequency === self::PERIOD_QUARTERLY ? 3 : 1;
        $cursor = Carbon::parse(config("declarations.start_from.{$type}"))->startOfMonth();
        $limit = Carbon::today()->startOfMonth()->addMonthsNoOverflow($months);

        $choices = [];

        for ($i = 0; $i < 60 && $cursor->lte($limit); $i++) {
            if (! in_array($cursor->toDateString(), $existingStarts, true)) {
                $choices[$cursor->toDateString()] = self::periodLabel($cursor, $months);
            }

            $cursor = $cursor->copy()->addMonthsNoOverflow($months);
        }

        return array_reverse($choices, true);
    }

    private static function periodLabel(Carbon $start, int $months): string
    {
        if ($months === 1) {
            return ucfirst($start->translatedFormat('F Y'));
        }

        $end = $start->copy()->addMonthsNoOverflow($months - 1);
        $quarter = intdiv($start->month - 1, 3) + 1;

        return sprintf('T%d %s (%s – %s)', $quarter, $start->format('Y'), ucfirst($start->translatedFormat('M')), ucfirst($end->translatedFormat('M')));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::saving(function (Declaration $declaration): void {
            if (! $declaration->isDirty('status')) {
                return;
            }

            if ($declaration->status === 'filed' && ! $declaration->filed_at) {
                $declaration->filed_at = now();
            }

            if ($declaration->status === 'paid' && ! $declaration->paid_at) {
                $declaration->paid_at = now();
            }
        });
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

    /**
     * Fréquence de la période, déduite du nombre de mois couverts (aucune colonne
     * dédiée : la cadence choisie à la création est déjà entièrement encodée par
     * period_start/period_end/covered_months).
     */
    protected function frequencyLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $months = count($this->covered_months ?? []);

            return match ($months) {
                1 => 'Mensuel',
                3 => 'Trimestriel',
                default => $months > 0 ? "{$months} mois" : '—',
            };
        });
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
