<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use App\Models\Supplier;
use Filament\Forms\Form;
use App\Models\SupplierInvoice;
use Filament\Resources\Pages\Page;
use App\Exceptions\MistralException;
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
use App\Services\Models\ExtractSupplierInvoiceData;
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
                                    $error = $state['state'] ?? null;
                                    $warning = $state['supplier_id'] ?? null;
                                    if ($error === 'Erreur') {
                                        return 'bg-red-100 dark:bg-red-800';
                                    }
                                    if ($warning == 1) {
                                        return 'bg-blue-100 dark:bg-orange-800';
                                    }
                                    return 'bg-white dark:bg-white/5';
                                })
                                ->label('Données des factures')
                                ->collapsed()
                                ->addable(false)
                                ->itemLabel(fn($state) => sprintf('%s (%s)', $state['file_name'] ?? 'Fichier inconnu', $state['state'] ?? ''))
                                ->extraItemActions([
                                    Action::make('retry')
                                        ->icon('heroicon-s-arrow-path')
                                        ->action(function (array $arguments, Repeater $component, $set) {
                                            $itemData = $component->getItemState($arguments['item']);
                                            $this->retryFileAnalysis($itemData, $set);
                                        })
                                ])
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
                                        ->icon('heroicon-s-eye')
                                        ->color('success')
                                        ->action(function (array $arguments, Repeater $component): void {
                                            $itemData = $component->getRawItemState($arguments['item']);
                                            $si = SupplierInvoice::find($itemData['id']);
                                            redirect(SupplierInvoiceResource::getUrl('edit', ['record' => $si->id]));
                                        })
                                ])
                        ]),
                ])
            ]);
    }

    protected function handleMultipleFileUploads(array $files, callable $set): void
    {
        $analyzer = app(ExtractSupplierInvoiceData::class);
        $invoiceData = [];
        $errors = [];

        foreach ($files as $file) {
            $data = ['file_name' => $file->getClientOriginalName()];

            try {
                $response = $analyzer->analyze($file);

                if ($response->isSuccess()) {
                    $data = array_merge($data, $response->getDataArray());
                    $data['state'] = 'Succès';
                    $data['error_comment'] = null;
                } else {
                    $data['state'] = 'Erreur';
                    $data['error_comment'] = $response->getMessage();
                    $errors[] = "{$data['file_name']}: {$response->getMessage()}";
                }
            } catch (MistralException $e) {
                $data['state'] = 'Erreur';
                $data['error_comment'] = 'Erreur IA : ' . $e->getMessage();
                $errors[] = "{$data['file_name']}: " . $e->getMessage();
            } catch (\Throwable $e) {
                $data['state'] = 'Erreur';
                $data['error_comment'] = 'Erreur interne : ' . $e->getMessage();
                $errors[] = "{$data['file_name']}: Erreur interne";
            }

            $invoiceData[] = $data;
        }

        $set('invoice_data', $invoiceData);

        if (!empty($errors)) {
            \Filament\Notifications\Notification::make()
                ->title('Analyse partielle terminée')
                ->body(implode("\n", $errors))
                ->danger()
                ->send();
        }
    }


    public function retryFileAnalysis(array $itemData, callable $set): void
    {
        $fileName = $itemData['file_name'] ?? null;

        if (!$fileName) {
            Notification::make()
                ->title('Erreur')
                ->body('Nom du fichier introuvable.')
                ->danger()
                ->send();
            return;
        }

        $file = collect($this->file_pdf_image)->first(
            fn($file) => $file->getClientOriginalName() === $fileName
        );

        if (!$file) {
            Notification::make()
                ->title('Erreur')
                ->body('Fichier source introuvable.')
                ->danger()
                ->send();
            return;
        }

        $analyzer = app(\App\Services\Models\ExtractSupplierInvoiceData::class);
        $updatedData = $itemData;

        try {
            $response = $analyzer->analyze($file);

            if ($response->isSuccess()) {
                $updatedData = array_merge($updatedData, $response->getDataArray());
                $updatedData['state'] = 'Succès';
                $updatedData['error_comment'] = null;
            } else {
                $updatedData['state'] = 'Erreur';
                $updatedData['error_comment'] = $response->getMessage();
            }
        } catch (\App\Exceptions\MistralException $e) {
            $updatedData['state'] = 'Erreur';
            $updatedData['error_comment'] = 'Erreur IA : ' . $e->getMessage();
        } catch (\Throwable $e) {
            $updatedData['state'] = 'Erreur';
            $updatedData['error_comment'] = 'Erreur interne : ' . $e->getMessage();
        }

        $invoiceData = collect($this->invoice_data)
            ->map(fn($data) => $data['file_name'] === $fileName ? $updatedData : $data)
            ->toArray();

        $set('invoice_data', $invoiceData);

        Notification::make()
            ->title("Réanalyse de {$fileName}")
            ->body($updatedData['error_comment'] ?? 'Analyse réussie')
            ->{$updatedData['state'] === 'Erreur' ? 'danger' : 'success'}()
            ->send();
    }
}
