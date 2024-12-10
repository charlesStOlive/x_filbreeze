<?php

namespace App\Listeners;

use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SupplierInvoiceFileAdded
{
    /**
     * Handle the event.
     */
    public function handle(MediaHasBeenAddedEvent|Media $event): void
    {
        if ($event instanceof MediaHasBeenAddedEvent) {
            Log::info('MediaHasBeenAdded event déclenché');
            // $file = $event->media;
            // $invoice = $event->media->model;

            // if ($invoice instanceof \App\Models\SupplierInvoice) {
            //     $supplierSlug = $invoice->supplier->slug ?? 'unknown-supplier';
            //     $invoiceDate = $invoice->invoice_at_my;
            //     $newFileName = "{$supplierSlug}-{$file->file_name}";
            //     $invoice->sharepoint_path = "x_factures/{$invoiceDate}/{$newFileName}";
                
            //     Storage::disk('sharepoint')->put($invoice->sharepoint_path, file_get_contents($file->getPath()));
            //     $invoice->saveQuietly();
            // }
        }

        if ($event instanceof Media) { // Traite l'événement `eloquent.deleted`
            Log::info("Le fichier media avec l'ID {$event->id} a été supprimé.");
            // $invoice = $event->model;

            // if ($invoice instanceof \App\Models\SupplierInvoice) {
            //     Log::info("Suppression de média associée à la facture ID : " . $invoice->id);
                
            //     // Logique de suppression additionnelle, comme supprimer un fichier dans SharePoint
            //     if (Storage::disk('sharepoint')->exists($invoice->sharepoint_path)) {
            //         Storage::disk('sharepoint')->delete($invoice->sharepoint_path);
            //     } else {
            //         Log::warning("Le fichier SharePoint {$invoice->sharepoint_path} n'existe pas.");
            //     }
            // }
        }
    }
}
