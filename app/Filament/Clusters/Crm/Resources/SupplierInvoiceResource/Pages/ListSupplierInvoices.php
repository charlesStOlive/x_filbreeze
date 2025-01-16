<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use Filament\Actions\Action;
use App\Models\SupplierInvoice;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Wizard\Step;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use App\Services\Models\SupplierInvoiceFileAnalyser;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\CreatSupplieFromFile;

class ListSupplierInvoices extends ListRecords
{
    protected static string $resource = SupplierInvoiceResource::class;
    protected SupplierInvoiceFileAnalyser $fileAnalyzer;
    protected ?string $tempFilePath = null; // Stocker le chemin temporaire du fichier traité

    public string $modalCloseButtonLabel = 'Abandonner';
    public ?SupplierInvoice $createdInvoice = null;

    public function resetPopupState(): void
    {
        $this->modalCloseButtonLabel = 'Abandonner';
        $this->createdInvoice = null;
        $this->tempFilePath = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFromFile')
                ->label('Créer à partir de fichiers')
                ->url(fn() => SupplierInvoiceResource::getUrl('createfromfile'))
                ->icon('heroicon-s-document-currency-euro')
                ->color('success'),
        ];
    }
}
