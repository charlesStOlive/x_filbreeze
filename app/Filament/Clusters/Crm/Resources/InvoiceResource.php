<?php

namespace App\Filament\Clusters\Crm\Resources;

use Str;
use App\Models\Quote;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Product;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Filament\Clusters\Crm;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables\Grouping\Group;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Helpers\ProductFormHelper;
use Filament\Forms\Components\Builder\Block;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Forms\Components\MarkdownEditor;
use App\Filament\Components\Tables\DateColumn;
use Filament\Forms\Components\Builder as FormBuilder;
use App\Filament\Infolists\Components\MermaidDiagramEntry;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;
use A909M\FilamentStateFusion\Tables\Filters\StateFusionSelectFilter;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages\PreviewPdf;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages\EditInvoice;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages\ListInvoices;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-s-document-currency-dollar';

    protected static ?string $cluster = Crm::class;

    protected static $quoteCache = [];

    public static function getLabel(): string
    {
        return 'Factures clients';
    }


    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('company.title')
                    ->label('Client'),
                Group::make('submited_at_my')
                    ->label('Soumis Ans/Mois')
                    ->getKeyFromRecordUsing(fn($record) => $record->submited_at_my ?? 'Non défini')
                    ->orderQueryUsing(fn(Builder $query) => $query->orderBy('submited_at_my', 'desc')),
                Group::make('payed_at_my')
                    ->label('Payement Ans/Mois')
                    ->getKeyFromRecordUsing(fn($record) => $record->payed_at_my ?? 'Non payé')
                    ->orderQueryUsing(fn(Builder $query) => $query->orderBy('payed_at_my', 'desc')),

            ])
            ->defaultGroup('payed_at_my')
            ->groupingDirectionSettingHidden()
            ->columns([
                TextColumn::make('code')
                    ->sortable()
                    ->description(fn($record): string => Str::limit($record->title, 35))
                    ->searchable(['code', 'title']),
                TextColumn::make('state')
                    ->badge(),
                TextColumn::make('company.title')
                    ->sortable()
                    ->description(fn($record): string => $record->contact->full_name),
                DateColumn::make('submited_at')
                    ->sortable(),
                DateColumn::make('payed_at')
                    ->sortable(),
                TextColumn::make('total_ht')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()),
                TextColumn::make('total_ttc')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()),
                TextColumn::make('tva')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()),
                DateColumn::make('created_at')
                    ->toggleable(isToggledHiddenByDefault: true),
                DateColumn::make('updated_at')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                StateFusionSelectFilter::make('state')
                    ->multiple()->default(['draft', 'submited', 'payed'])
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('voir_schema')
                    ->label('Voir le schéma')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->modalHeading('Diagramme des États - Invoice')
                    ->modalDescription('Visualisation des états et transitions du modèle Invoice')
                    ->infolist([
                        \App\Filament\Infolists\Components\MermaidDiagramEntry::make('states_diagram')
                            ->modelClass(Invoice::class)
                            ->height('500px')
                            ->theme('default')
                            ->lazy()
                    ])
                    ->modalWidth('7xl'),
                Action::make('test_mermaid')
                    ->label('Test Mermaid')
                    ->icon('heroicon-o-bug-ant')
                    ->color('warning')
                    ->url(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getContactAndCompanyFields($companyEditable = true): array
    {
        return [
            Select::make('contact_id')
                ->label('Contact')
                ->relationship(
                    name: 'contact',
                    titleAttribute: 'full_name',
                    modifyQueryUsing: fn($query, $get) => $get('company_id') ? $query->where('company_id', $get('company_id')) : $query,
                )
                ->searchable(fn($get) => $get('company_id') ? false : true)
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $contact = Contact::find($state);
                        if ($contact && $contact->company_id) {
                            $set('company_id', $contact->company_id);
                        }
                    }
                }),
            Select::make('company_id')
                ->label('Client')
                ->relationship('company', 'title')
                ->searchable()
                ->required()
                ->reactive()
                ->disabled(!$companyEditable)
                ->dehydrated(fn($state) => filled($state)),

        ];
    }

    protected static function getQuote($quoteId)
    {
        if (!isset(self::$quoteCache[$quoteId])) {
            self::$quoteCache[$quoteId] = Quote::find($quoteId);
        }
        return self::$quoteCache[$quoteId];
    }

    protected static function updateQuoteFields($quote, callable $set, $record)
    {
        if ($quote) {
            $set('total_quote', $quote->total_ht);
            $set('total_quote_left', Invoice::getAmountLeft($quote, $record));
        }
    }

    protected static function generateMarkdownContent($quote, $facturation)
    {
        return view('filament.clusters.crm.resources.invoices.md.auto_description', compact('quote', 'facturation'))->render();
    }

    public static function getItemsBuilderComponent(): array
    {
        return [
            Section::make('Elements de la facture')
                ->schema([
                    FormBuilder::make('items')
                        ->label(false)
                        ->addActionLabel('Ajouter un élément à la facture')
                        ->collapsed()
                        ->live()
                        ->cloneable()
                        ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateItemsTotal($set, $get, $livewire))
                        ->blocks([
                            self::getProductBlock(),
                            self::getOnQuoteBlock(),
                            self::getTMABlock(),
                            self::getRemiseBlock(),
                            self::getForfaitBlock(),
                            self::getTasksBlock(),
                        ])
                        ->columnSpanFull(),
                ])
        ];
    }


    protected static function getProductBlock()
    {
        return Block::make('product')
            ->icon('fas-box')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Produit';
                }
                return sprintf('%s %s (%s €HT)', 'Produit : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                Select::make('product_id')
                    ->label('Produit')
                    ->preload()
                    ->searchable()
                    ->options(function (callable $get) {
                        $products = Product::with('gamme')->get();

                        // Cas du produit supprimé
                        $selectedId = $get('product_id');
                        $selectedProduct = $selectedId ? Product::find($selectedId) : null;

                        $grouped = $products
                            ->groupBy(fn($product) => $product->gamme?->name ?? 'Autre')
                            ->mapWithKeys(fn($group) => [
                                $group->first()->gamme->name ?? 'Autre' => $group->pluck('title', 'id')->toArray()
                            ])
                            ->toArray();

                        // Si produit manquant, l'ajouter manuellement avec libellé personnalisé
                        if ($selectedId && !$selectedProduct) {
                            $grouped['⚠️ Produits supprimés'] = [
                                $selectedId => "❌ Produit supprimé (ID $selectedId)"
                            ];
                        }

                        return $grouped;
                    })
                    ->getSearchResultsUsing(
                        fn(string $search) =>
                        Product::with('gamme')
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->limit(15)
                            ->get()
                            ->groupBy(fn($product) => $product->gamme?->name ?? 'Autre')
                            ->mapWithKeys(fn($group) => [
                                $group->first()->gamme->name ?? 'Autre' => $group->pluck('title', 'id')->toArray()
                            ])
                            ->toArray()
                    )
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if (!$state) return;

                        $product = Product::with('companies')->find($state);
                        if (!$product) return;

                        $companyId = $get('../../../../company_id');
                        $company = $companyId ? Company::find($companyId) : null;

                        $set('cu', ProductFormHelper::getPriceForCompany($product, $company));
                        $set('product_title', $product->title);
                        $set('product_code', $product->code);
                        $set('type', $product->type->value);

                        if (!$get('title')) {
                            $set('title', $product->title);
                        }
                    }),

                Hidden::make('product_title')->dehydrated(),

                TextInput::make('product_code')
                    ->label('Code produit')
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn(callable $get) => filled($get('product_code'))),

                TextInput::make('title')
                    ->label('Titre personnalisé')
                    ->required()
                    ->visible(fn(callable $get) => filled($get('product_id')))
                    ->reactive()
                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                        if (!$state && $get('product_title')) {
                            $set('title', $get('product_title'));
                        }
                    }),

                Hidden::make('type')->dehydrated(),

                MarkdownEditor::make('description')
                    ->label('Description élement')
                    ->columnSpanFull()
                    ->disableToolbarButtons([
                        'attachFiles',
                        'table',
                    ]),

                Grid::make(2)
                    ->schema(
                        fn(callable $get) =>
                        $get('type')
                            ? ProductFormHelper::getDynamicFormFields(
                                $get('type'),
                                function ($set, $get, $livewire) {
                                    $type = $get('type');
                                    if ($type === 'forfait_a') {
                                        self::updateItemsTotal($set, $get, $livewire, true);
                                    } else {
                                        self::updateProductTotal($set, $get, $livewire);
                                    }
                                }
                            )
                            : []
                    )
                    ->columns(3),
            ])
            ->columns(3);
    }




    protected static function getTMABlock()
    {
        return Block::make('tma')
            ->icon('fas-ticket')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'TMA';
                }
                return sprintf('TMA du %s au %s ', $state['start_at'] ?? 'inc',  $state['end_at'] ?? 'inc');
            })
            ->schema([
                TextInput::make('start_at')
                    ->label('Debut')
                    ->type('month'),
                TextInput::make('end_at')
                    ->label('Fin')
                    ->type('month'),
                TextInput::make('qty_total')
                    ->label('Nombre de ticket')
                    ->numeric(),
                TextInput::make('qty_facturable')
                    ->label('Nombre de ticket facturable')
                    ->numeric(),
                TextInput::make('qty')
                    ->label('Nombre Heures facturables')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                TextInput::make('cu')
                    ->label('Cout heure')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                TextInput::make('total')
                    ->label('total')
                    ->numeric(),
            ])
            ->columns(3);
    }

    protected static function getOnQuoteBlock()
    {
        return Block::make('on_quote')
            ->icon('fas-file-invoice')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Depuis devis';
                }
                $quoteId = $state['quote_id'] ?? null;
                if (!$quoteId) {
                    return 'Depuis devis';
                }
                $quote = self::getQuote($quoteId);
                return sprintf('Facturation depuis devis %s montant %s', $quote->code, $state['total'] ?? 0);
            })
            ->schema([
                Select::make('quote_id')
                    ->label('Select Quote')
                    ->options(function (callable $get) {
                        $companyId = $get('../../../company_id');
                        //\Log::info('company_id : ' . $companyId);
                        return Quote::where('state', 'validated')
                            ->where('company_id', $companyId)
                            ->withRemainingAmount()
                            ->pluck('title', 'id');
                    })
                    ->required()
                    ->reactive()
                    ->afterStateHydrated(function ($state, callable $set, $record) {
                        if ($state) {
                            $quote = self::getQuote($state);
                            self::updateQuoteFields($quote, $set, $record);
                        }
                    })
                    ->afterStateUpdated(function ($state, callable $set, $record) {
                        //\Log::info('state');
                        //\Log::info($state);
                        if ($state) {
                            $quote = self::getQuote($state);
                            self::updateQuoteFields($quote, $set, $record);
                        }
                    })
                    ->columnSpanFull(),

                TextInput::make('total_quote')
                    ->label('Total Facturable')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('total_quote_left')
                    ->label('Total Restant à facturer')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('billing_percentage')
                    ->label('%')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->live(onBlur: true)
                    ->hintActions([
                        Action::make('p_30')
                            ->label('30%')
                            ->action(function ($get, $set, $livewire) {
                                $set('total', round($get('total_quote') * 30 / 100, 2));
                                self::updateQuoteTotal($set, $get, $livewire, 'total');
                            }),
                        Action::make('p_40')
                            ->label('40%')
                            ->action(function ($get, $set, $livewire) {
                                $set('total', round($get('total_quote') * 40 / 100, 2));
                                self::updateQuoteTotal($set, $get, $livewire, 'total');
                            }),
                        Action::make('p_full')
                            ->label('fin')
                            ->action(function ($get, $set, $livewire) {
                                $set('total', $get('total_quote_left'));
                                self::updateQuoteTotal($set, $get, $livewire, 'total');
                            }),
                    ])
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateQuoteTotal($set, $get, $livewire, 'billing_percentage')),
                TextInput::make('total')
                    ->label('Total')
                    ->dehydrated()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateQuoteTotal($set, $get, $livewire, 'total'))
                    ->rule(function (callable $get) {
                        return 'lte:' . ($get('total_quote_left') ?? 0);
                    }),
                \Filament\Schemas\Components\Actions::make([
                    Action::make('auto_create')
                        ->icon('heroicon-m-clipboard')
                        ->label('Auto remplir titre et description')
                        ->action(function ($get, $set) {
                            $quoteId = $get('quote_id');
                            if ($quoteId) {
                                $quote = self::getQuote($quoteId);
                                //\Log::info($quote->toArray());
                                //\Log::info($quote->code);
                                if ($quote) {
                                    $facturation = [
                                        'total_quote' =>  $get('total_quote'),
                                        'total_quote_left' =>  $get('total_quote_left'),
                                        'billing_percentage' =>  $get('billing_percentage'),
                                        'total' =>  $get('total')
                                    ];
                                    $markdownContent = self::generateMarkdownContent($quote, $facturation);
                                    $set('title', 'Facturation devis n°' . $quote->code);
                                    $set('description', $markdownContent);
                                }
                            }
                            return;
                        })
                ])->columnSpanFull(),
                TextInput::make('title')
                    ->label('Titre élement')
                    ->required()
                    ->reactive()
                    ->columnSpanFull(),
                MarkdownEditor::make('description')
                    ->label('Description élement')
                    ->columnSpanFull()
                    ->disableToolbarButtons([
                        'attachFiles',
                        'table',
                    ]),
            ])
            ->columns(4);
    }

    protected static function getRemiseBlock()
    {
        return Block::make('remise')
            ->icon('fas-percentage')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Remise';
                }
                return sprintf('%s %s (%s €HT)', 'Remise : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->live(onBlur: true),
            ])
            ->columns(2);
    }

    protected static function getForfaitBlock()
    {
        return Block::make('forfait')
            ->icon('fas-check-circle')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Forfait libre';
                }
                return sprintf('%s %s (%s €HT)', 'Forfait : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->live(onBlur: true)
            ])
            ->columns(3);
    }

    protected static function getTasksBlock()
    {
        return Block::make('tasks')
            ->icon('fas-calculator')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Taches libre';
                }
                return sprintf('%s %s (%s €HT)', 'Taches : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                TextInput::make('cu')
                    ->label('Total U')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                TextInput::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
            ])
            ->columns(3);
    }

    public static function getDuplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Dupliquer')
            ->icon('heroicon-s-document-duplicate')
            ->color('gray')
            ->modalHeading('Dupliquer la facture')
            ->modalDescription(new HtmlString("Attention cette action permet de <b>dupliquer</b> une facture <br> l'état sera réinitialisé "))
            ->fillForm(fn($record): array => [
                'client_id' => $record->client_id,
                'contact_id' => $record->contact_id,
                'title' => $record->title,
            ])
            ->schema([
                ...InvoiceResource::getContactAndCompanyFields(),
                TextInput::make('title')
                    ->label('Titre')
                    ->required(),
            ])
            ->action(function ($record, $data) {
                $newRecord = $record->createNewReplication($data);
                return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $newRecord]));
            });
    }

    public static function updateProductTotal(callable $set, callable $get, $livewire)
    {
        $type = $get('type') ?? null;
        $cu = $get('cu') ?? 0;
        $qty = $get('qty') ?? 1;

        $total = match ($type) {
            'heures', 'jours', 'forfait_m', 'forfait_u' => $cu * $qty,
            'forfait_a' => $cu,
            default => 0,
        };

        $set('total', round($total, 2));
        self::updateItemsTotal($set, $get, $livewire, true);
    }


    public static function updateTaskTotal(callable $set, callable $get, $livewire)
    {
        $cu = $get('cu') ?? 0;
        $qty = $get('qty') ?? 0;
        $total = $cu * $qty;
        $set('total', $total);
        self::updateItemsTotal($set, $get, $livewire, true);
    }

    public static function updateQuoteTotal(callable $set, callable $get, $livewire, $fieldSrc)
    {
        $billingPercentage = $get('billing_percentage') ?? 0;
        $totalQuote = $get('total_quote') ?? 0;
        $total = $get('total') ?? 0;
        if ($fieldSrc == 'billing_percentage') {
            $total = round($totalQuote * $billingPercentage / 100, 2);
            $set('total', $total);
        } else if ($fieldSrc == 'total') {
            $billingPercentage = $totalQuote ? round(($total / $totalQuote) * 100, 2) : 0;
            $set('billing_percentage', $billingPercentage);
        }
        self::updateItemsTotal($set, $get, $livewire, true);
    }

    public static function updateItemsTotal(callable $set, callable $get, $livewire, $parent = false)
    {
        $items = $get('items') ?? [];
        $tx_tva = $get('tx_tva') ?? 0;
        if ($parent) {
            $items = $get('../../..') ?? [];
            $tx_tva = $get('../../../tx_tva') ?? 0;
        }
        $totals = collect($items)
            ->partition(fn($item) => $item['type'] === 'remise');

        // Calcule la somme des totaux des remises
        $totalRemise = $totals[0]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        // Calcule la somme des totaux des autres éléments
        $totalHtBr = $totals[1]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        // Mettre à jour total_ht_br
        $totalHt = $totalHtBr - $totalRemise;
        $tva = round($totalHt * $tx_tva, 2);
        $totalTTC = round($totalHt + $tva, 2);
        if ($parent) {
            $set('../../../total_ht_br', $totalHtBr);
            $set('../../../total_ht', $totalHt);
            $set('../../../tva', $tva);
            $set('../../../total_ttc', $totalTTC);
        } else {
            $set('total_ht_br', $totalHtBr);
            $set('total_ht', $totalHt);
            $set('tva', $tva);
            $set('total_ttc', $totalTTC);
        }
        $livewire->dispatch('totalsUpdated');
    }

    public static function getBasicItemsField()
    {
        return [
            TextInput::make('title')
                ->label('Titre élement')
                ->required()
                ->reactive()
                ->columnSpanFull(),
            MarkdownEditor::make('description')
                ->label('Description élement')
                ->columnSpanFull()
                ->disableToolbarButtons([
                    'attachFiles',
                    'table',
                ])

        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Diagramme des États - API FilamentStateFusion')
                    ->description('Utilise l\'API FilamentStateFusion pour récupérer les données')
                    ->schema([
                        MermaidDiagramEntry::make('states_diagram')
                            ->modelClass(Invoice::class)
                            ->height('500px')
                            ->theme('default')
                            ->type('flowchart')     // Options: flowchart, graph, stateDiagram, journey, gantt
                            ->direction('TB')       // Options: LR, RL, TB (TD), BT  
                            ->lazy()
                    ])
                    ->collapsible(),

                Section::make('Diagramme des États - Trait HasMermaidStateDiagram')
                    ->description('Utilise directement le trait HasMermaidStateDiagram du modèle Invoice')
                    ->schema([
                        MermaidDiagramEntry::make('states_diagram_trait')
                            ->label('États et Transitions (Trait)')
                            ->apiEndpoint(function (Invoice $record) {
                                return route('api.states.mermaid-json-from-trait', [
                                    'model' => 'Invoice',
                                    'id' => $record->id ?? 'new'
                                ]);
                            })
                            ->height('500px')
                            ->theme('default')
                            ->type('flowchart')
                            ->direction('LR')       // Direction horizontale pour différencier
                            ->lazy()
                    ])
                    ->collapsible()
            ]);
    }

    public static function getEloquentQuery(): EloquentBuilder
    {
        return parent::getEloquentQuery()
            ->with(['contact', 'company']);
    }

    public static function getRelations(): array
    {
        return [];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'edit' => EditInvoice::route('/{record}/edit'),
            'preview-pdf' => PreviewPdf::route('/{record}/preview-pdf'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
