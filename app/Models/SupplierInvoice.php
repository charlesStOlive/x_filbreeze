<?php

namespace App\Models;

use Carbon\Carbon;
use Spatie\ModelStates\HasStates;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Models\States\SupplierInvoice\SupplierInvoiceState;
use CharlesStOlive\FilamentStateFusionEnhanced\Traits\HasMermaidStateDiagram;

/**
 * @mixin IdeHelperSupplierInvoice
 */
class SupplierInvoice extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use HasStates;
    use HasMermaidStateDiagram;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_supplier_invoices';

    protected $guarded = ['id'];

    protected $casts = [
        'state' => SupplierInvoiceState::class,
    ];

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

    /**
     * Upload le fichier vers SharePoint (appelé lors de la validation)
     */
    public function uploadToSharePoint(): void
    {
        $file = $this->getFirstMedia('invoice');

        if (!$file) {
            return;
        }

        try {
            // Vérification : si sharepoint_path existe déjà et que le fichier existe sur SharePoint
            if (!empty($this->sharepoint_path)) {
                if (Storage::disk('sharepoint')->exists($this->sharepoint_path)) {
                    \Log::info("Fichier SharePoint déjà existant, upload ignoré: {$this->sharepoint_path}");
                    return;
                }
                // Le path existe en DB mais pas sur SharePoint, on continue l'upload
                \Log::warning("Path SharePoint en DB mais fichier absent, re-upload: {$this->sharepoint_path}");
            }

            $supplierSlug = $this->supplier->slug ?? 'unknown-supplier';
            
            // invoice_at_my est calculé automatiquement par MySQL (colonne générée)
            $invoiceDate = $this->invoice_at_my;
            
            $newFileName = "{$supplierSlug}-{$file->file_name}";
            $sharepointPath = "x_factures/{$invoiceDate}/{$newFileName}";

            Storage::disk('sharepoint')->put($sharepointPath, file_get_contents($file->getPath()));

            $this->sharepoint_path = $sharepointPath;
            $this->saveQuietly();

            \Log::info("Fichier uploadé vers SharePoint: {$sharepointPath}");
        } catch (\Exception $e) {
            \Log::error("Erreur lors de l'upload SharePoint: " . $e->getMessage());
            // On ne bloque pas la validation si SharePoint échoue
        }
    }

    /**
     * Supprimer le fichier de SharePoint (appelé lors de l'annulation de validation)
     */
    public function deleteFromSharePoint(): void
    {
        if (empty($this->sharepoint_path)) {
            return;
        }

        try {
            if (Storage::disk('sharepoint')->exists($this->sharepoint_path)) {
                Storage::disk('sharepoint')->delete($this->sharepoint_path);
                \Log::info("Fichier supprimé de SharePoint: {$this->sharepoint_path}");
            } else {
                \Log::warning("Le fichier SharePoint {$this->sharepoint_path} n'existe pas.");
            }

            $this->sharepoint_path = null;
            $this->saveQuietly();
        } catch (\Exception $e) {
            \Log::error("Erreur lors de la suppression SharePoint: " . $e->getMessage());
            // On ne bloque pas la transition si SharePoint échoue
        }
    }
}
