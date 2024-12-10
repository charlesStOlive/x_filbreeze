<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quote extends Model
{
    use HasFactory;

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
    protected $guarded = [
        'validated_at_my'
    ];

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
        'has_tva',
        'tx_tva',
        'tva',
        'end_at',
        'total_ttc',
        'payed_at',
        'submited_at',
    ];



    protected $casts = [
        'items' => 'json'
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
            if (is_null($model->state)) {
                $model->status = 'draft';
            }
            if ($model->version == 1) {
                $model->is_retained = true;
            }
        });
    }


    public function getModelCode()
    {
        $clientId = $this->company_id;

        // Formatage de l'ID du client en 3 chiffres
        $clientCode = str_pad($clientId, 3, '0', STR_PAD_LEFT);

        // Compter les devis existants pour ce client avec version == 1
        $count = static::where('company_id', $clientId)
            ->where('is_retained')
            ->count();

        // Incrémenter le compteur pour obtenir le prochain numéro
        $quoteNumber = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

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

    public function createNewVersion() : Quote
    {
        $newRecord = $this->replicate();
        \Log::info($newRecord->end_at);
        $newRecord->version = Quote::where('code', $this->code)->max('version') + 1;
        $newRecord->is_retained = false;
        unset($newRecord->created_at_my);
        unset($newRecord->validated_at_my);
        unset($newRecord->validated_at);
        unset($newRecord->validated_at_qy);
        $newRecord->save();
        return $newRecord;
    }

    public function createNewReplication($data) : Quote
    {
        $newRecord = $this->replicate();
        $newRecord->version = 1;
        $newRecord->is_retained = true;
        $newRecord->status = 'draft';
        $newRecord->fill($data);
        unset($newRecord->created_at_my);
        unset($newRecord->validated_at_my);
        unset($newRecord->validated_at);
        unset($newRecord->validated_at_qy);
        $newRecord->save();
        return $newRecord;
    }

    public function cleanUnactiveTest() : int
    {
        $count = Quote::where('code', $this->code)->where('is_retained', false)->where('status', '<>' , 'validated')
            ->count();
        \Log::info($count);
        
        return $count;
    }
    public function cleanUnactive() : bool
    {
        Quote::where('code', $this->code)->where('is_retained', false)->where('status', '<>' , 'validated')
            ->delete();
        return true;
    }
}
