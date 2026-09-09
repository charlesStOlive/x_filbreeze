<?php

namespace App\Models;

use App\Traits\HasTextExtraction;
use Spatie\ModelStates\HasStates;
use App\Services\Models\ItemsManager;
use App\Models\States\Quote\QuoteState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperQuote
 */
class Quote extends Model
{
    use HasFactory;
    use HasTextExtraction;
    use HasStates;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_quotes';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'title',
        'state',
        'company_id',
        'contact_id',
        'description',
        'items',
        'remise',
        'total_ht_br',
        'total_ht',
        'total_avant_options',
        'total_options',
        'total_jours',
        'has_tva',
        'tx_tva',
        'tva',
        'end_at',
        'total_ttc',
        'payed_at',
        'submited_at',
    ];

    /**
     * Configuration des champs à extraire et injecter trait 
     */
    protected $getTextes = [
        'title',
        'description',
        'items.*.data.title',
        'items.*.data.description',
    ];



    protected $casts = [
        'items' => 'json',
        'state' => QuoteState::class,
    ];

    /**
     * BELONGS
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'crm_quotes_invoices')
            ->withPivot('billing_percentage')
            ->withTimestamps();
    }

    /**
     * Hook sur la création pour générer automatiquement le code
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (is_null($model->code)) {
                $model->code = $model->getModelCode();
            }
            if (is_null($model->version)) {
                $model->version = 1;
            }
            if ($model->version == 1) {
                $model->is_retained = true;
            }
        });

        static::saving(function ($quote) {
            $quote->recalculateTotalsFromItems();
        });
    }

    public function recalculateTotalsFromItems(): self
    {
        if (! is_array($this->items)) {
            return $this;
        }

        $items = collect($this->items)->map(function (array $item): array {
            $type = $item['type'] ?? null;
            $productType = data_get($item, 'data.type');

            if (
                $type === 'tasks'
                || ($type === 'product' && in_array($productType, ['heures', 'jours', 'forfait_m', 'forfait_u'], true))
            ) {
                $item['data']['total'] = $this->lineTotalFromItem($item);
            }

            return $item;
        });

        $this->items = $items->all();

        $totals = $items->partition(fn ($item) => ($item['type'] ?? null) === 'remise');
        $billableItems = $totals[1];

        $totalRemise = $totals[0]->sum(fn ($item) => $this->lineTotalFromItem($item));
        $totalHtBr = $billableItems->sum(fn ($item) => $this->lineTotalFromItem($item));
        $totalAvantOptions = $billableItems
            ->filter(fn ($item) => empty($item['data']['is_option']))
            ->sum(fn ($item) => $this->lineTotalFromItem($item));
        $totalOptions = $billableItems
            ->filter(fn ($item) => ! empty($item['data']['is_option']))
            ->sum(fn ($item) => $this->lineTotalFromItem($item));
        $totalJours = $billableItems
            ->filter(fn ($item) => ($item['type'] ?? null) === 'product')
            ->sum(function ($item): float {
                return match (data_get($item, 'data.type')) {
                    'jours' => (float) data_get($item, 'data.qty', 0),
                    'heures' => (float) data_get($item, 'data.qty', 0) / 8,
                    default => 0,
                };
            });

        $this->total_ht_br = round($totalHtBr, 2);
        $this->total_ht = round($totalHtBr - $totalRemise, 2);
        $this->total_avant_options = round($totalAvantOptions, 2);
        $this->total_options = round($totalOptions, 2);
        $this->total_jours = round($totalJours, 2);

        return $this;
    }

    protected function lineTotalFromItem(array $item): float
    {
        $type = $item['type'] ?? null;
        $data = $item['data'] ?? [];
        $productType = $data['type'] ?? null;

        if ($type === 'tasks' && isset($data['cu'], $data['qty'])) {
            return round((float) $data['cu'] * (float) $data['qty'], 2);
        }

        if ($type === 'product' && in_array($productType, ['heures', 'jours', 'forfait_m', 'forfait_u'], true) && isset($data['cu'], $data['qty'])) {
            return round((float) $data['cu'] * (float) $data['qty'], 2);
        }

        return round((float) ($data['total'] ?? 0), 2);
    }

    public function scopeWithRemainingAmount($query)
    {
        return $query->where(function ($query) {
            $query->where('total_ht', '>', function ($subquery) {
                $subquery->selectRaw('COALESCE(SUM(crm_quotes_invoices.total), 0)')
                    ->from('crm_quotes_invoices')
                    ->whereColumn('crm_quotes_invoices.quote_id', 'crm_quotes.id');
            });
        });
    }

    public function getModelNumber()
    {
        $clientId = $this->company_id;
        $number = static::where('company_id', $clientId)->max('number');
        return $number + 1;
    }

    public function getModelCode()
    {
        $clientId = $this->company_id;
        // Formatage de l'ID du client en 3 chiffres
        $clientCode = str_pad($clientId, 3, '0', STR_PAD_LEFT);
        // Compter les devis existants pour ce client avec version == 1
        if (!$this->number) {
            $this->number = $this->getModelNumber();
        }
        // Incrémenter le compteur pour obtenir le prochain numéro
        $quoteNumber = str_pad($this->number, 3, '0', STR_PAD_LEFT);
        // Générer le code final
        return "D_{$clientCode}_{$quoteNumber}";
    }

    public function swapRetainedQuote()
    {
        Quote::where('code', $this->code)
            ->update(['is_retained' => false]);
        $this->is_retained = true;
        $this->save();
    }

    public function createNewVersion($data): Quote
    {
        $newRecord = $this->replicate();
        $newRecord->fill($data);
        $newRecord->version = Quote::where('code', $this->code)->max('version') + 1;
        $newRecord->is_retained = false;
        unset($newRecord->created_at_my);
        unset($newRecord->validated_at_my);
        unset($newRecord->validated_at);
        unset($newRecord->validated_at_qy);
        $newRecord->save();
        return $newRecord;
    }

    public function createNewReplication($data): Quote
    {
        $newRecord = $this->replicate();
        $newRecord->version = 1;
        $newRecord->code = null;
        $newRecord->is_retained = true;
        $newRecord->state = null;
        $newRecord->number = null;
        $newRecord->fill($data);
        unset($newRecord->created_at_my);
        unset($newRecord->validated_at_my);
        unset($newRecord->validated_at);
        unset($newRecord->validated_at_qy);
        $newRecord->save();
        return $newRecord;
    }

    public function hasOneVersionValidated(): bool
    {
        if ($this->state == 'validated') {
            return true;
        }
        $otherExiste = Quote::where('code', $this->code)->where('state', 'validated')->count();
        if ($otherExiste) {
            return true;
        }
        return false;
    }

    public function cleanUnactiveTest(): int
    {
        $count = Quote::where('code', $this->code)->where('is_retained', false)->where('state', '<>', 'validated')
            ->count();
        return $count;
    }
    public function cleanUnactive(): bool
    {
        Quote::where('code', $this->code)->where('is_retained', false)->where('state', '<>', 'validated')
            ->delete();
        return true;
    }
}
