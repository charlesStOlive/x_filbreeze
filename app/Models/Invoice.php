<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Contact;

use App\Traits\HasTextExtraction;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;
    use HasTextExtraction;

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
        'status',
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


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (is_null($model->code)) {
                $model->code = $model->getModelCode();
            }
            if (is_null($model->state)) {
                $model->status = 'draft';
            }
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
        return "F_{$clientCode}_{$quoteNumber}";
    }

    public function createNewReplication($data): Invoice
    {
        $newRecord = $this->replicate();
        $newRecord->code = null;
        $newRecord->number = null;
        $newRecord->status = 'draft';
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
