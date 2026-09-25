<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource;
use CharlesStOlive\FilamentPrism\Filament\Actions\AiResourceAction;
use Filament\Resources\Pages\ListRecords;

class ListSupplierInvoices extends ListRecords
{
    protected static string $resource = SupplierInvoiceResource::class;

    public ?string $grouping = 'supplier.name';

    public function getGroupedSelectableTableRecordKeys(?string $group): array
    {
        // Si le groupe est null, retourner un tableau vide
        if ($group === null) {
            return [];
        }
        
        // Extraire seulement la clé du groupe, en ignorant la direction de tri
        if (str_contains($group, ':')) {
            $group = explode(':', $group)[0];
        }
        
        return parent::getGroupedSelectableTableRecordKeys($group);
    }

    protected function getHeaderActions(): array
    {
        return [
            AiResourceAction::make('createFromFile')
                ->aiResource('supplier-invoice-extraction')
                ->label('Créer à partir de fichiers')
                ->icon('heroicon-s-document-currency-euro')
                ->color('success'),
        ];
    }
}
