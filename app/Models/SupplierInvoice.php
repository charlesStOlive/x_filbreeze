<?php

namespace App\Models;

use Carbon\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class SupplierInvoice extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_supplier_invoices';

    protected $guarded = ['id'];

    // Propriété statique pour stocker le chemin de l'ancien fichier
    protected static $oldFilePath = null;

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('invoice')
            ->singleFile();
    }

    // protected static function booted()
    // {
    //     // Avant de sauvegarder, calcul des attributs basés sur invoice_at
    //     static::saving(function ($invoice) {
    //         if ($invoice->invoice_at) {
    //             $date = Carbon::parse($invoice->invoice_at);
    //             $invoice->invoice_at_my = $date->format('Y_m'); // Format ANNEE_MOIS (ex: 2024_11)
    //             $year = $date->format('Y');
    //             $quarter = ceil($date->month / 3); // Calcul du trimestre
    //             $invoice->invoice_at_qy = "{$year}_Q{$quarter}";
    //         }
    //     });
    // }
}
