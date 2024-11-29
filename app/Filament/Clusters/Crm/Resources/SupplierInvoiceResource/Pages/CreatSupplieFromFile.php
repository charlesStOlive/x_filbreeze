<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use App\Models\Supplier;
use Filament\Forms\Form;
use App\Models\SupplierInvoice;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Cache;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Forms\Components\NotilacRepeater;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use App\Services\Models\SupplierInvoiceFileAnalyser;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource;

class CreatSupplieFromFile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SupplierInvoiceResource::class;

    protected static string $view = 'filament.clusters.crm.resources.supplier-invoice-resource.pages.creat-supplie-from-file';

    // Déclarations des propriétés pour Livewire
    public ?array $file_pdf_image = [];
    public ?array $invoice_data = [];
    public ?array $processedInvoices = [];

    protected SupplierInvoiceFileAnalyser $fileAnalyzer;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    // Étape 1 : Charger des fichiers
                    Wizard\Step::make('Ajouter des fichiers')
                        ->schema([
                            FileUpload::make('file_pdf_image')
                                ->label('Charger un ou plusieurs fichiers (PDF ou image)')
                                ->required()
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->multiple()
                        ])
                        ->afterValidation(function ($get, $set) {
                            $this->handleMultipleFileUploads($get('file_pdf_image'), $set);
                        }),

                    // Étape 2 : Vérification des informations
                    Wizard\Step::make('Vérifier les informations')
                        ->schema([
                            NotilacRepeater::make('invoice_data')
                                ->itemColor(function ($state) {
                                    $mystate = $state['state'] ?? false;
                                    \Log::info('state : ' . $mystate);
                                    return match ($state['state'] ?? null) {
                                        'Erreur' => 'bg-primary-500',
                                        default => 'bg-white',
                                    };
                                })
                                ->label('Données des factures')
                                ->collapsed()
                                ->addable(false)
                                ->itemLabel(fn($state) => sprintf('%s (%s)', $state['file_name'] ?? 'Fichier inconnu', $state['state'] ?? ''))
                                ->schema([
                                    TextInput::make('file_name')
                                        ->label('Nom du fichier')
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(2),
                                    Textarea::make('error_comment')
                                        ->label('Commentaire d\'erreur')
                                        ->hidden(fn($get) => $get('state') !== 'Erreur')
                                        ->disabled()
                                        ->dehydrated()
                                        ->rows(3)
                                        ->columnSpan(2),
                                    Fieldset::make('Informations de la Facture')
                                        ->schema([
                                            Select::make('supplier_id')
                                                ->options(fn() => Supplier::pluck('name', 'id'))
                                                ->label('Supplier ID')
                                                ->columnSpan(2),
                                            DatePicker::make('invoice_at')->label('Date de la facture')->format('Y/m/d'),
                                            TextInput::make('invoice_number')->label('Numéro de la facture'),
                                            TextInput::make('currency')->label('Devise'),
                                            Toggle::make('has_tva')->label('TVA ? '),
                                            TextInput::make('total_ht')->label('Total HT'),
                                            TextInput::make('tva')->label('TVA'),
                                            TextInput::make('tx_tva')->label('Taux de TVA'),
                                            TextInput::make('total_ttc')->label('Total TTC'),
                                        ])
                                        ->hidden(fn($get) => $get('state') === 'Erreur')
                                        ->columns(5),
                                ])
                                ->columns(1),
                        ])
                        ->afterValidation(function ($get, $set) {
                            $this->createSupplierInvoices($get);
                        }),

                    // Étape 3 : Confirmation
                    Wizard\Step::make('Confirmation')
                        ->schema([
                            Repeater::make('processedInvoices')
                                ->label('Factures créées')
                                ->reorderable(false)
                                ->addable(false)
                                ->simple(
                                    TextInput::make('name')->label('Nom')->disabled(),
                                )
                                ->deleteAction(
                                    fn(Action $action) => $action->hidden(true),
                                )
                                ->extraItemActions([
                                    Action::make('show_item')
                                        ->icon('heroicon-o-eye')
                                        ->color('success')
                                        ->action(function (array $arguments, Repeater $component): void {
                                            $itemData = $component->getRawItemState($arguments['item']);
                                            $si = SupplierInvoice::find($itemData['id']);
                                            \Log::info($si);
                                            redirect(SupplierInvoiceResource::getUrl('edit', ['record' => $si->id]));
                                        })
                                ])
                        ]),
                ])
            ]);
    }

    protected function handleMultipleFileUploads(array $files, callable $set): void
    {
        $this->fileAnalyzer = app(SupplierInvoiceFileAnalyser::class);
        $cacheKey = 'test_invoice_data'; // Clé unique pour le cache
        $invoiceData = [];

        // Vérifier si les données sont déjà en cache
        // if (false) {
        Cache::forget('test_invoice_data');
        if (Cache::has($cacheKey)) {
            $invoiceData = Cache::get($cacheKey);
        } else {
            foreach ($files as $file) {
                $data = [];
                if ($file instanceof TemporaryUploadedFile) {
                    $tempPath = $file->getRealPath();
                    $response = $this->fileAnalyzer->analyzeFile($tempPath);

                    $data['file_name'] = $file->getClientOriginalName(); // Récupérer le nom du fichier source

                    if ($response->isSuccess()) {
                        $data = array_merge($data, $response->getDataArray());
                        $data['state'] = 'Succès';
                        $data['error_comment'] = null; // Pas de commentaire pour le succès
                    } else {
                        $data['state'] = 'Erreur';
                        $data['error_comment'] = $response->getMessage(); // Message d'erreur
                    }
                }
                $invoiceData[] = $data;
            }

            // Mettre en cache les données pour 1 heure
            // Cache::put($cacheKey, $invoiceData, now()->addHour());
        }

        // \Log::info($invoiceData);

        // Associer les données d'invoice
        $set('invoice_data', $invoiceData);
    }

    public function createSupplierInvoices($get): void
    {
        $invoices = $get('invoice_data');
        $files = $get('file_pdf_image');
        foreach ($invoices as $data) {
            if ($data['state'] === 'Erreur') {
                continue;
            }
            $supplierInvoice = SupplierInvoice::create($data);



            foreach ($files as $file) {
                \Log::info($file->getClientOriginalName());
                \Log::info($data['file_name']);
                if ($file->getClientOriginalName() === $data['file_name']) {
                    $supplierInvoice->addMedia($file)->toMediaCollection('invoice');
                }
                // Ajoutez des conditions pour filtrer le bon fichier si nécessaire

            }

            $this->processedInvoices[] = [
                'id' => $supplierInvoice->id,
                'name' => $supplierInvoice->invoice_number,
            ];
        }

        Notification::make()
            ->title('Factures créées avec succès.')
            ->success()
            ->send();
    }

    public function deleteInvoice(int $invoiceId): void
    {
        $invoice = SupplierInvoice::find($invoiceId);

        if ($invoice) {
            $invoice->delete();
            $this->processedInvoices = array_filter($this->processedInvoices, fn($invoice) => $invoice['id'] !== $invoiceId);
        }
    }
}
