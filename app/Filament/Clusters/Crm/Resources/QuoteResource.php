<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Tables\Columns\TextColumn;
use Str;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Components\Grid;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use App\Filament\Clusters\Crm\Resources\QuoteResource\Pages\ListQuotes;
use App\Filament\Clusters\Crm\Resources\QuoteResource\Pages\EditQuote;
use App\Filament\Clusters\Crm\Resources\QuoteResource\Pages\PreviewPdf;
use Filament\Forms;
use Filament\Tables;
use App\Models\Quote;
use Filament\Actions;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Company;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Builder;
use App\Filament\ModelStates\StateColumn;
use Filament\Tables\Actions\CreateAction;
use App\Services\Helpers\ProductFormHelper;
use App\Filament\Components\Tables\DateColumn;
use App\Filament\ModelStates\StateSelectFilter;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Filament\Clusters\Crm\Resources\QuoteResource\RelationManagers;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static string | \BackedEnum | null $navigationIcon = 'fas-file-invoice';

    protected static ?string $cluster = Crm::class;

    public static function getLabel(): string
    {
        return 'Devis';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->sortable()
                    ->description(fn($record): string => Str::limit($record->title, 35))
                    ->searchable(['title', 'code']),
                StateColumn::make('state')
                    ->badge(),
                TextColumn::make('company.title')
                    ->sortable()
                    ->description(fn($record): string => Str::limit($record->contact->full_name, 35))
                    ->searchable(['title']),
                TextColumn::make('is_retained')
                    ->searchable(),
                TextColumn::make('version')
                    ->searchable(),
                TextColumn::make('total_ht')
                    ->numeric()
                    ->sortable(),
                DateColumn::make('end_at'),
                DateColumn::make('validated_at'),
                DateColumn::make('created_at')
                    ->toggleable(isToggledHiddenByDefault: true),
                DateColumn::make('updated_at')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                StateSelectFilter::make('state')
                    ->multiple()->default(['draft', 'validated'])
            ])
            ->recordActions([
                EditAction::make(),
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
            Select::make('company_id')
                ->label('Client')
                ->relationship('company', 'title')
                ->searchable()
                ->required()
                ->live(onBlur: true)
                ->disabled(!$companyEditable),

            Select::make('contact_id')
                ->label('Contact')
                ->relationship(
                    name: 'contact',
                    titleAttribute: 'full_name',
                    modifyQueryUsing: fn($query, $get) => $get('company_id') ? $query->where('company_id', $get('company_id')) : $query,
                )
                ->searchable(fn($get) => $get('company_id') ? false : true)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $contact = Contact::find($state);
                        if ($contact && $contact->company_id) {
                            $set('company_id', $contact->company_id);
                        }
                    }
                }),


        ];
    }

    public static function getItemsBuilderComponent(): array
    {
        return [
            Fieldset::make('Elements du devis')
                ->schema([
                    Builder::make('items')
                        ->label(false)
                        ->addActionLabel('Ajouter un élément au devis')
                        ->collapsed()
                        ->live()
                        ->cloneable()
                        ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateItemsTotal($set, $get, $livewire))
                        ->blocks([
                            self::getProductBlock(),
                            self::getForfaitBlock(),
                            self::getTasksBlock(),
                            self::getRemiseBlock(),
                        ])
                        ->columnSpanFull(),
                ])
        ];
    }

    protected static function getProductBlock()
    {
        return Block::make('product')
            ->icon('fas-box')
            ->label(function (?array $state) {
                if ($state === null) {
                    return 'Produit';
                }

                $title = $state['title'] ?? 'inc';
                $total = $state['total'] ?? 0;
                $type = $state['type'] ?? '';
                $qty = $state['qty'] ?? 0;
                $isOption = $state['is_option'] ?? false;

                // Ajouter la quantité entre crochets pour heures et jours
                $qtyDisplay = '';
                if (in_array($type, ['heures', 'jours']) && $qty > 0) {
                    $suffix = $type === 'heures' ? 'h' : 'j';
                    $qtyDisplay = " [{$qty}{$suffix}]";
                }

                $baseText = sprintf('%s %s%s (%s €HT)', 'Produit : ', $title, $qtyDisplay, $total);

                // Ajouter couleur si c'est une option
                if ($isOption) {
                    return new HtmlString('<span style="color: #10b981; font-weight: 600;">' . htmlspecialchars($baseText) . ' [OPTION]</span>');
                }

                return $baseText;
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
                    ->live()
                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                        if (!$state && $get('product_title')) {
                            $set('title', $get('product_title'));
                        }
                    }),

                Toggle::make('is_option')
                    ->label('Ligne en option')
                    ->default(false)
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateItemsTotal($set, $get, $livewire, true)),

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
                                    // Pour les types qui ont qty/cu, on utilise updateProductTotal
                                    // Pour FORFAIT_A qui définit total directement, on utilise updateItemsTotal
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

    protected static function getForfaitBlock()
    {
        return Block::make('forfait')
            ->icon('fas-check-circle')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Forfait';
                }
                return sprintf('%s %s (%s €HT)', 'Forfait : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->live()
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateItemsTotal($set, $get, $livewire, true))
            ])
            ->columns(3);
    }

    protected static function getTasksBlock()
    {
        return Block::make('tasks')
            ->icon('fas-calculator')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Taches';
                }
                return sprintf('%s %s (%s €HT)', 'Taches : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                TextInput::make('cu')
                    ->label('Total U')
                    ->numeric()
                    ->live()
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                TextInput::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->live()
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
            ])
            ->columns(3);
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
                    ->live()
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateItemsTotal($set, $get, $livewire, true)),
            ])
            ->columns(2);
    }

    public static function getDuplicateAction()
    {
        return Action::make('duplicate')
            ->label('Dupliquer')
            ->icon('heroicon-s-document-duplicate')
            ->modalHeading('Dupliquer')
            ->modalDescription(new HtmlString("Attention cette action permet de <b>dupliquer</b> un devis <br> pour créer une nouvelle version cliquez sur nouvelle vesion dans la page d'édition "))
            ->fillForm(fn($record): array => [
                'client_id' => $record->client_id,
                'contact_id' => $record->contact_id,
            ])
            ->schema([
                ...self::getContactAndCompanyFields(),
                TextInput::make('title')
                    ->label('Titre')
                    ->required(),
                DatePicker::make('end_at')
                    ->label('Fin')
                    ->default(now()->addMonth())
                    ->required()
            ])
            ->action(function ($record, $data) {
                $newRecord = $record->createNewReplication($data);
                return redirect()->to(QuoteResource::getUrl('edit', ['record' => $newRecord]));
            });
    }

    public static function updateItemsTotal(callable $set, callable $get, $livewire, $parent = false)
    {
        $items = $get('items') ?? [];
        if ($parent) {
            $items = $get('../../..') ?? [];
        }

        $totals = collect($items)
            ->partition(fn($item) => $item['type'] === 'remise');

        $totalRemise = $totals[0]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        $totalHtBr = $totals[1]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        // Ajout : total sans les lignes en option
        $totalAvOption = $totals[1]
            ->filter(fn($item) => empty($item['data']['is_option'])) // lignes non optionnelles
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        $totalOptions = $totals[1]
            ->filter(fn($item) => !empty($item['data']['is_option'])) // lignes optionnelles
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        // Calcul du total de jours (uniquement pour les produits type HEURES et JOURS)
        $totalJours = $totals[1]
            ->filter(fn($item) => $item['type'] === 'product') // seulement les produits
            ->filter(fn($item) => in_array($item['data']['type'] ?? '', ['heures', 'jours'])) // seulement heures et jours
            ->map(function ($item) {
                $type = $item['data']['type'] ?? '';
                $qty = $item['data']['qty'] ?? 0;

                if ($type === 'jours') {
                    return $qty; // directement en jours
                } elseif ($type === 'heures') {
                    return $qty / 8; // conversion heures -> jours (8h = 1 jour)
                }

                return 0;
            })
            ->sum();

        $totalHt = $totalHtBr - $totalRemise;

        if ($parent) {
            $set('../../../total_ht_br', $totalHtBr);
            $set('../../../total_ht', $totalHt);
            $set('../../../total_avant_options', $totalAvOption);
            $set('../../../total_options', $totalOptions);
            $set('../../../total_jours', round($totalJours, 2));
        } else {
            $set('total_ht_br', $totalHtBr);
            $set('total_ht', $totalHt);
            $set('total_avant_options', $totalAvOption);
            $set('total_options', $totalOptions);
            $set('total_jours', round($totalJours, 2));
        }

        $livewire->dispatch('totalsUpdated');
    }


    public static function updateTaskTotal(callable $set, callable $get, $livewire)
    {
        // Récupérer les valeurs de cu et qty

        $cu = $get('cu') ?? 0;
        $qty = $get('qty') ?? 0;
        // Calculer le total pour ce bloc
        $total = $cu * $qty;
        // Mettre à jour le champ total
        $set('total', $total);
        // Appeler la mise à jour globale du total_ht
        self::updateItemsTotal($set, $get, $livewire, true);
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


    public static function getBasicItemsField()
    {
        return [
            TextInput::make('title')
                ->label('Titre élement')
                ->required()
                ->live()
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

    public static function getActionActivateQuote()
    {
        return [
            Actions::make([
                Action::make('activate_v')
                    ->label(fn($record) => $record->is_retained ? 'Devis Actif' : 'Activer ce devis')
                    ->disabled(fn($record) => $record->is_retained)
                    ->action(function ($record) {
                        $record->swapRetainedQuote();
                    })
                    ->color(fn($record) => $record->is_retained ? 'success' : 'danger'),
            ])->fullWidth(),
        ];
    }


    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotes::route('/'),
            'edit' => EditQuote::route('/{record}/edit'),
            'preview-pdf' => PreviewPdf::route('/{record}/preview-pdf'),
        ];
    }
}
