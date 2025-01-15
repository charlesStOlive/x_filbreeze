<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Contact;

use App\Traits\HasTextExtraction;
use Spatie\ModelStates\HasStates;
use Illuminate\Database\Eloquent\Model;
use App\Models\States\Invoice\InvoiceState;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;
    use HasTextExtraction;
    use HasStates;


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_invoices';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'title',
        'state',
        'modalite',
        'company_id',
        'contact_id',
        'description',
        'items',
        'total_ht_br',
        'total_ht',
        'has_tva',
        'tx_tva',
        'tva',
        'total_ttc',
        'payed_at',
        'validated_at',
        'created_at',
        'updated_at',
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
        'submited_at' => 'datetime',
        'payed_at' => 'datetime',
        'state' => InvoiceState::class,

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

    public function quotes()
    {
        return $this->belongsToMany(Quote::class, 'crm_quotes_invoices')
            ->withPivot('billing_percentage')
            ->withTimestamps();
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (is_null($model->code)) {
                $model->code = $model->getModelCode();
            }
        });

        static::saved(function ($invoice) {
            if (isset($invoice->items)) {
                $quotesData = collect($invoice->items)
                    ->filter(fn($item) => $item['type'] === 'onQuote') // Vérifie le type d'item
                    ->mapWithKeys(function ($item) {
                        \Log::info('item!!!', $item);
                        return [
                            $item['data']['quote_id'] => [
                                'total_quote' => $item['data']['total_quote'],
                                'total_quote_left' => $item['data']['total_quote_left'],
                                'billing_percentage' => $item['data']['billing_percentage'],
                                'total' => $item['data']['total']
                            ],
                        ];
                    })
                    ->toArray();
                static::syncLinkedQuoteAmountLeft($invoice, $quotesData);
                // Vérifier le pourcentage total de facturation pour chaque devis
                
            }
        });
    }

    /**
     * Attributs
     */
    public static function syncLinkedQuoteAmountLeft($invoice, $quotesData) {
        \Log::info('quotesData!!!', $quotesData);
        foreach ($quotesData as $quoteId => $pivotData) {
                    $quote = \App\Models\Quote::find($quoteId);
                    if ($quote) {
                        $currentAmount = static::getAmountFactured($quote, $invoice);
                        $newTotal = $currentAmount + $pivotData['total'];
                        if ($newTotal > $quote->total_ht) {
                            throw ValidationException::withMessages([
                                'items' => "The billing percentage for quote {$quote->code} exceeds amount",
                            ]);
                        }
                    }
                }
                // Synchroniser les relations
                $invoice->quotes()->sync($quotesData);
    }

    public static function getAmountFactured($quote, $invoice) {
        $currentAmount = $quote->invoices()->where('invoice_id', '!=', $invoice->id)->sum('crm_quotes_invoices.total');
        return $currentAmount;
    }

    public static function getAmountLeft($quote, $invoice) {
        $currentAmount = round($quote->total_ht - static::getAmountFactured($quote, $invoice), 2);
        return $currentAmount;
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
        return "F_{$clientCode}_{$quoteNumber}";
    }

    public function createNewReplication($data): Invoice
    {
        $newRecord = $this->replicate();
        $newRecord->code = null;
        $newRecord->number = null;
        $newRecord->state = null;
        $newRecord->fill($data);
        unset($newRecord->created_at);
        unset($newRecord->created_at_my);
        unset($newRecord->created_at_qy);
        //
        unset($newRecord->submited_at);
        unset($newRecord->submited_at_my);
        unset($newRecord->submited_at_qy);
        //
        unset($newRecord->payed_at);
        unset($newRecord->payed_at_qy);
        unset($newRecord->payed_at_my);
        $newRecord->save();
        return $newRecord;
    }
}
