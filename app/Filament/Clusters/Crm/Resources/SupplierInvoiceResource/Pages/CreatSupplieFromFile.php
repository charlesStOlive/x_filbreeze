<?php 

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource;
use App\Models\SupplierInvoice;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use App\Services\Models\SupplierInvoiceFileAnalyser;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class CreatSupplieFromFile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SupplierInvoiceResource::class;

    protected static string $view = 'filament.clusters.crm.resources.supplier-invoice-resource.pages.creat-supplie-from-file';

    protected SupplierInvoiceFileAnalyser $fileAnalyzer;
    protected ?string $tempFilePath = null; // Stocker le chemin temporaire du fichier traité
    public ?SupplierInvoice $createdInvoice = null;

    // Déclarer les propriétés utilisées dans les champs de formulaire
    public ?string $supplier_id = null;
    public ?string $has_tva = null;
    public ?string $total_ht = null;
    public ?string $tva = null;
    public ?string $tx_tva = null;
    public ?string $total_ttc = null;
    public ?string $invoice_at = null;
    public ?string $invoice_number = null;
    public ?string $currency = null;
    public ?array $file_pdf_image = [];
    
    public ?string $prompt = null;
    public ?string $type = null;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    // Étape 1 : Charger un fichier
                    Wizard\Step::make('Ajouter un fichier')
                        ->schema([
                            FileUpload::make('file_pdf_image')
                                ->label('Charger un fichier (PDF ou image)')
                                ->required()
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ])
                        ->afterValidation(function ($get, $set) {
                            if ($get('file_pdf_image')) {
                                $this->handleFileUpload(null, $get, $set);
                            }
                        }),

                    // Étape 2 : Vérification des informations
                    Wizard\Step::make('Vérifier l\'information')
                        ->schema([
                            Fieldset::make('Information générale')
                                ->schema([
                                    TextInput::make('type')
                                        ->label('Type de contenu')
                                        ->disabled()
                                        ->hidden(fn($get) => !$get('type'))->columnSpanFull(),
                                    Textarea::make('prompt')
                                        ->label('Prompt mistral for agent')
                                        ->rows(5)
                                        ->disabled()
                                        ->hidden(fn($get) => !$get('prompt'))->columnSpanFull(),
                                ])->columnSpan(1),

                            Fieldset::make('Informations de la Facture')
                                ->schema([
                                    TextInput::make('supplier_id')->label('Supplier ID')->columnSpanFull(),
                                    TextInput::make('has_tva')->label('TVA incluse')->columnSpanFull(),
                                    TextInput::make('total_ht')->label('Total HT')->columnSpanFull(),
                                    TextInput::make('tva')->label('TVA')->columnSpanFull(),
                                    TextInput::make('tx_tva')->label('Taux de TVA')->columnSpanFull(),
                                    TextInput::make('total_ttc')->label('Total TTC')->columnSpanFull(),
                                    TextInput::make('invoice_at')->label('Date de la facture')->columnSpanFull(),
                                    TextInput::make('invoice_number')->label('Numéro de la facture')->columnSpanFull(),
                                    TextInput::make('currency')->label('Devise')->columnSpanFull(),
                                ])->columnSpan(1),
                        ])->columns(2)
                        ->afterValidation(function ($get, $set) {
                            if ($get('file_pdf_image')) {
                                $this->createSupplierInvoice($get);
                            }
                        }),

                    // Étape 3 : Confirmation
                    Wizard\Step::make('Confirmation')
                        ->schema([
                            FormActions::make([
                                FormAction::make('openInvoice')
                                    ->label('Ouvrir la facture créée')
                                    ->color('success')
                                    ->url(fn() => $this->createdInvoice
                                        ? SupplierInvoiceResource::getUrl('edit', ['record' => $this->createdInvoice])
                                        : '#', shouldOpenInNewTab: true),
                                FormAction::make('deleteInvoice')
                                    ->label('Supprimer la facture créée')
                                    ->color('danger')
                                    ->action(function () {
                                        if ($this->createdInvoice) {
                                            $this->createdInvoice->delete();
                                        }
                                    }),
                            ]),
                        ]),
                ])
            ]);
    }

    protected function handleFileUpload($fileComponent, $get, $set): void
    {
        $temporaryFile = $fileComponent ? $fileComponent->getState() : $get('file_pdf_image');
        $uploadedFile = reset($temporaryFile);

        if ($uploadedFile instanceof TemporaryUploadedFile) {
            $originalName = $uploadedFile->getClientOriginalName();
            $tempPath = $uploadedFile->getRealPath();

            // Déplacement vers un répertoire temporaire
            $temporaryDirectory = TemporaryDirectory::make();
            $tmpFilePath = $temporaryDirectory->path($originalName);

            copy($tempPath, $tmpFilePath);

            // Analyse du fichier avec SupplierInvoiceFileAnalyser
            $this->fileAnalyzer = app(SupplierInvoiceFileAnalyser::class); // Injection via IoC
            $response = $this->fileAnalyzer->analyzeFile($tmpFilePath);

            // Gestion de la réponse
            if ($response->isSuccess()) {
                $set('type', 'success');
                $set('prompt', $this->fileAnalyzer->mistralPrompt);

                // Stocker le chemin temporaire pour un usage ultérieur
                $this->tempFilePath = $tmpFilePath;

                // Injecter les données extraites dans les champs
                foreach ($response->getDataArray() as $key => $value) {
                    $set($key, $value);
                }
            } else {
                Notification::make()
                    ->title('Erreur lors de l\'analyse du fichier.')
                    ->body($response->getMessage())
                    ->danger()
                    ->send();
                $set('type', 'error');
            }
        }
    }

    public function createSupplierInvoice($get): void
    {
        $invoiceData = [
            'supplier_id' => $get('supplier_id'),
            'has_tva' => $get('has_tva'),
            'total_ht' => $get('total_ht'),
            'tva' => $get('tva'),
            'tx_tva' => $get('tx_tva'),
            'total_ttc' => $get('total_ttc'),
            'invoice_at' => $get('invoice_at'),
            'invoice_number' => $get('invoice_number'),
            'currency' => $get('currency'),
        ];

        // Création de la facture
        $supplierInvoice = SupplierInvoice::create($invoiceData);

        // Association du fichier à la facture
        $files = $get('file_pdf_image');
        $file = reset($files); // Obtenir le premier fichier du tableau
        if ($file instanceof TemporaryUploadedFile) {
            $supplierInvoice->addMedia($file)->toMediaCollection('invoice');
        } else {
            \Log::info('Aucun fichier n\'a été trouvé pour la facture.');
        }

        Notification::make()
            ->title('Facture créée avec succès.')
            ->success()
            ->send();

        $this->createdInvoice = $supplierInvoice;
    }
}
