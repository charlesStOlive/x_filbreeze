<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Actions\Action;
use Filament\Schemas\Components\Fieldset;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Forms\Components\NotilacRepeater; // Je garde ton composant custom
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Services\Models\SupplierInvoiceAnalysisService; // Le nouveau service
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource;
use Illuminate\Support\Facades\Log;

class CreatSupplieFromFileV2 extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SupplierInvoiceResource::class;
    protected string $view = 'filament.clusters.crm.resources.supplier-invoice-resource.pages.creat-supplie-from-file';

    public ?array $file_pdf_image = [];
    public ?array $invoice_data = [];
    public ?array $processedInvoices = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    // --- ÉTAPE 1 : UPLOAD ---
                    Step::make('Ajouter des fichiers')
                        ->schema([
                            FileUpload::make('file_pdf_image')
                                ->label('Charger fichiers (PDF/Image)')
                                ->required()
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->multiple()
                        ])
                        ->afterValidation(function ($get, $set) {
                            $this->handleAnalysis($get('file_pdf_image'), $set);
                        }),

                    // --- ÉTAPE 2 : VÉRIFICATION ---
                    Step::make('Vérifier les informations')
                        ->schema([
                            NotilacRepeater::make('invoice_data')
                                ->itemColor(function ($state) {
                                    if (empty($state['supplier_id'])) return 'bg-orange-100 dark:bg-orange-900/50';
                                    return 'bg-white dark:bg-white/5';
                                })
                                ->label('Données extraites')
                                ->addable(false)
                                ->extraItemActions([
                                    Action::make('retry')
                                        ->icon('heroicon-s-arrow-path')
                                        ->action(fn($arguments, $component, $set) => $this->retryFile($arguments, $component, $set))
                                ])
                                ->schema([
                                    // Header de l'item
                                    TextInput::make('file_name')->disabled()->columnSpan(2),

                                    // Zone d'erreur
                                    Textarea::make('error_comment')
                                        ->hidden(fn($get) => $get('state') !== 'Erreur')
                                        ->disabled()
                                        ->columnSpan(2),

                                    // Formulaire Facture
                                    Fieldset::make('Détails Facture')
                                        ->schema([
                                            TextInput::make('supplier_name_from_invoice')
                                                ->label('Nom trouvé sur la facture')
                                                ->disabled()
                                                ->dehydrated(true) // Important pour conserver la valeur
                                                ->columnSpan(2)
                                                ->helperText('Ce nom sera enregistré comme nom alternatif si différent du fournisseur'),

                                            Select::make('supplier_id')
                                                ->label('Fournisseur')
                                                ->options(Supplier::pluck('name', 'id'))
                                                ->searchable()
                                                ->required() // On force l'utilisateur à choisir si l'IA a échoué
                                                ->columnSpan(2)
                                                ->helperText(fn($state) => empty($state) ? '⚠️ Fournisseur non identifié automatiquement' : null),

                                            DatePicker::make('invoice_at')->label('Date'),
                                            TextInput::make('invoice_number')->label('N° Facture'),
                                            TextInput::make('currency')->label('Devise')->default('EUR'),

                                            Toggle::make('has_tva')->label('Soumis à TVA')->inline(false),

                                            TextInput::make('total_ht')->numeric()->label('Total HT'),
                                            TextInput::make('tva')->numeric()->label('Montant TVA'),
                                            TextInput::make('total_ttc')->numeric()->label('Total TTC'),
                                        ])
                                        ->hidden(fn($get) => $get('state') === 'Erreur')
                                        ->columns(4)
                                ])
                                ->columns(1)
                        ])
                        ->afterValidation(function ($get, $set) {
                            $this->saveInvoices($get, $set);
                        }),

                    // --- ÉTAPE 3 : CONFIRMATION ---
                    Step::make('Confirmation')
                        ->schema([
                            Repeater::make('processedInvoices')
                                ->label('Factures générées')
                                ->addable(false)
                                ->deletable(false)
                                ->simple(
                                    TextInput::make('name')->label('Numéro')->disabled()
                                )
                                ->extraItemActions([
                                    Action::make('voir')
                                        ->icon('heroicon-o-eye')
                                        ->url(fn($arguments, $component) => SupplierInvoiceResource::getUrl('edit', ['record' =>
                                        $component->getRawItemState($arguments['item'])['id']]))
                                ])
                        ]),
                ])
            ]);
    }

    // --- LOGIQUE MÉTIER ---

    protected function handleAnalysis(array $files, callable $set): void
    {
        $service = app(SupplierInvoiceAnalysisService::class); // Injection du nouveau service
        $results = [];
        $errors = [];

        foreach ($files as $file) {
            $fileName = $file->getClientOriginalName();
            $analysis = $service->analyze($file);

            $item = [
                'file_name' => $fileName,
                'state' => $analysis['success'] ? 'Succès' : 'Erreur',
            ];

            if ($analysis['success']) {
                $dto = $analysis['dto'];

                // On merge les données du DTO dans le tableau du repeater
                $item = array_merge($item, [
                    'supplier_id' => $analysis['supplier_id'],
                    'supplier_name_from_invoice' => $analysis['supplier_name_from_invoice'], // Nom trouvé sur facture
                    'invoice_number' => $dto->invoice_number,
                    'invoice_at' => $dto->invoice_at?->format('Y-m-d'), // Format pour DatePicker
                    'currency' => $dto->currency,
                    'total_ht' => $dto->total_ht,
                    'tva' => $dto->tva,
                    'tx_tva' => $dto->tx_tva,
                    'total_ttc' => $dto->total_ttc,
                    'has_tva' => ($dto->tva > 0),
                    'error_comment' => $analysis['warning'] ?? null,
                ]);
            } else {
                $item['error_comment'] = $analysis['error'];
                $errors[] = $fileName;
            }

            $results[] = $item;
        }

        $set('invoice_data', $results);

        if (!empty($errors)) {
            Notification::make()->warning()->title('Erreurs lors de l\'analyse')->body(implode(', ', $errors))->send();
        }
    }

    public function retryFile($arguments, $component, $set): void
    {
        // Logique de retry identique, appelle handleAnalysis pour un seul fichier
        // J'ai simplifié pour l'exemple, mais tu reprends la logique de ton ancien code
        // en appelant $service->analyze($foundFile);
    }

    protected function saveInvoices($get, $set): void
    {
        $dataRows = $get('invoice_data');
        $files = $get('file_pdf_image');
        $created = [];

        foreach ($dataRows as $row) {
            if (($row['state'] ?? '') === 'Erreur') continue;

            // Si un supplier est trouvé et que le nom sur la facture diffère, on l'ajoute aux alternative_names
            if (!empty($row['supplier_id']) && !empty($row['supplier_name_from_invoice'])) {
                $supplier = Supplier::find($row['supplier_id']);
                if ($supplier) {
                    $invoiceName = $row['supplier_name_from_invoice'];

                    // On vérifie que ce n'est pas déjà le nom exact du supplier
                    if (stripos($supplier->name, $invoiceName) === false && stripos($invoiceName, $supplier->name) === false) {
                        // Vérifier qu'aucun autre supplier n'a déjà ce nom alternatif
                        $conflictingSupplier = Supplier::where('id', '!=', $supplier->id)
                            ->where(function ($query) use ($invoiceName) {
                                $query->whereJsonContains('alternative_names', $invoiceName)
                                    ->orWhere('name', 'LIKE', "%$invoiceName%");
                            })
                            ->first();

                        if ($conflictingSupplier) {
                            Log::warning("Conflit détecté : '{$invoiceName}' existe déjà pour {$conflictingSupplier->name}");
                            Notification::make()
                                ->warning()
                                ->title('Conflit de nom alternatif')
                                ->body("Le nom '{$invoiceName}' existe déjà pour {$conflictingSupplier->name}. Non ajouté à {$supplier->name}.")
                                ->send();
                        } else {
                            // On ajoute le nom alternatif s'il n'existe pas déjà pour ce supplier
                            $alternativeNames = $supplier->alternative_names ?? [];
                            if (!in_array($invoiceName, $alternativeNames)) {
                                $alternativeNames[] = $invoiceName;
                                $supplier->alternative_names = $alternativeNames;
                                $supplier->save();
                                Log::info("Nom alternatif ajouté pour {$supplier->name}: {$invoiceName}");
                            }
                        }
                    }
                }
            }

            // Recherche du fichier correspondant
            $matchingFile = collect($files)->first(fn($f) => $f->getClientOriginalName() === $row['file_name']);

            if (!$matchingFile) {
                Log::error("Fichier source introuvable pour : {$row['file_name']}");
                Notification::make()
                    ->danger()
                    ->title('Fichier introuvable')
                    ->body("Le fichier '{$row['file_name']}' n'a pas pu être associé à la facture.")
                    ->send();
                continue; // On passe à la facture suivante
            }

            \Log::info("Création de la facture pour le fichier : {$row['file_name']}");

            // Création propre via Eloquent
            // Note: $row contient déjà les clés exactes (total_ht, etc) grâce au mapping précédent
            // On retire supplier_name_from_invoice qui n'est pas un champ de la table
            $invoiceData = collect($row)->except(['supplier_name_from_invoice', 'file_name', 'state', 'error_comment'])->toArray();
            $invoice = SupplierInvoice::create($invoiceData);

            // Rafraîchir le modèle pour récupérer les colonnes générées (invoice_at_my, etc.)
            $invoice->refresh();

            // Association du Média
            $invoice->addMedia($matchingFile)->toMediaCollection('invoice');

            // Transition automatique vers Validated (le handle() gère les redirections vers Warning/Error)
            $invoice->state->transitionTo(\App\Models\States\SupplierInvoice\Validated::class);

            $created[] = ['id' => $invoice->id, 'name' => $invoice->invoice_number ?? 'Sans numéro'];
        }

        $this->processedInvoices = $created;
        $set('processedInvoices', $created); // Synchroniser avec le state du form
        Notification::make()->success()->title(count($created) . ' factures créées')->send();
    }
}
