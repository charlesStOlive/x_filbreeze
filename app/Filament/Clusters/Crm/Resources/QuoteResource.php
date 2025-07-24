<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Quote;
use Filament\Actions;
use App\Models\Contact;
use App\Models\Product;
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

    protected static ?string $navigationIcon = 'fas-file-invoice';

    protected static ?string $cluster = Crm::class;

    public static function getLabel(): string
    {
        return 'Devis';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->sortable()
                    ->description(fn($record): string => \Str::limit($record->title, 35))
                    ->searchable(['title', 'code']),
                StateColumn::make('state')
                    ->badge(),
                Tables\Columns\TextColumn::make('company.title')
                    ->sortable()
                    ->description(fn($record): string => \Str::limit($record->contact->full_name, 35))
                    ->searchable(['title']),
                Tables\Columns\TextColumn::make('is_retained')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_ht')
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
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getContactAndCompanyFields($companyEditable = true): array
    {
        return [
            Forms\Components\Select::make('company_id')
                ->label('Client')
                ->relationship('company', 'title')
                ->searchable()
                ->required()
                ->live(onBlur:true)
                ->disabled(!$companyEditable),

            Forms\Components\Select::make('contact_id')
                ->label('Contact')
                ->relationship(
                    name: 'contact',
                    titleAttribute: 'full_name',
                    modifyQueryUsing: fn($query, $get) => $get('company_id') ? $query->where('company_id', $get('company_id')) : $query,
                )
                ->searchable(fn($get) => $get('company_id') ? false : true)
                ->required()
                ->live(onBlur:true)
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
            Forms\Components\Fieldset::make('Elements du devis')
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
        return Forms\Components\Builder\Block::make('product')
            ->icon('fas-box')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Produit';
                }
                return sprintf('%s %s (%s €HT)', 'Produit : ', $state['product_title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                Forms\Components\Select::make('product_id')
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

                Forms\Components\Hidden::make('product_title')->dehydrated(),

                Forms\Components\TextInput::make('product_code')
                    ->label('Code produit')
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn(callable $get) => filled($get('product_code'))),

                Forms\Components\TextInput::make('title')
                    ->label('Titre personnalisé')
                    ->required()
                    ->visible(fn(callable $get) => filled($get('product_id')))
                    ->live(onBlur:true)
                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                        if (!$state && $get('product_title')) {
                            $set('title', $get('product_title'));
                        }
                    }),

                Forms\Components\Toggle::make('is_option')
                    ->label('Ligne en option')
                    ->default(false)
                    ->columnSpanFull()
                    ->live(onBlur:true),

                Forms\Components\Hidden::make('type')->dehydrated(),

                Forms\Components\MarkdownEditor::make('description')
                    ->label('Description élement')
                    ->columnSpanFull()
                    ->disableToolbarButtons([
                        'attachFiles',
                        'table',
                    ]),

                Forms\Components\Grid::make('Détails')
                    ->label(false)
                    ->schema(
                        fn(callable $get) =>
                        $get('type')
                            ? ProductFormHelper::getDynamicFormFields($get('type'))
                            : []
                    )
                    ->columns(3),
            ])
            ->columns(3);
    }

    protected static function getForfaitBlock()
    {
        return Builder\Block::make('forfait')
            ->icon('fas-check-circle')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Forfait';
                }
                return sprintf('%s %s (%s €HT)', 'Forfait : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->live(onBlur: true)
            ])
            ->columns(3);
    }

    protected static function getTasksBlock()
    {
        return Forms\Components\Builder\Block::make('tasks')
            ->icon('fas-calculator')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Taches';
                }
                return sprintf('%s %s (%s €HT)', 'Taches : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                Forms\Components\TextInput::make('cu')
                    ->label('Total U')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                Forms\Components\TextInput::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
            ])
            ->columns(3);
    }

    protected static function getRemiseBlock()
    {
        return Builder\Block::make('remise')
            ->icon('fas-percentage')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Remise';
                }
                return sprintf('%s %s (%s €HT)', 'Remise : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->live(onBlur: true),
            ])
            ->columns(2);
    }

    public static function getDuplicateAction()
    {
        return Actions\Action::make('duplicate')
            ->label('Dupliquer')
            ->icon('heroicon-s-document-duplicate')
            ->modalHeading('Dupliquer')
            ->modalDescription(new HtmlString("Attention cette action permet de <b>dupliquer</b> un devis <br> pour créer une nouvelle version cliquez sur nouvelle vesion dans la page d'édition "))
            ->fillForm(fn($record): array => [
                'client_id' => $record->client_id,
                'contact_id' => $record->contact_id,
            ])
            ->form([
                ...self::getContactAndCompanyFields(),
                Forms\Components\TextInput::make('title')
                    ->label('Titre')
                    ->required(),
                Forms\Components\DatePicker::make('end_at')
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

        $totalHt = $totalHtBr - $totalRemise;

        if ($parent) {
            $set('../../../total_ht_br', $totalHtBr);
            $set('../../../total_ht', $totalHt);
            $set('../../../total_avant_options', $totalAvOption);
            $set('../../../total_options', $totalOptions);
        } else {
            $set('total_ht_br', $totalHtBr);
            $set('total_ht', $totalHt);
            $set('total_avant_options', $totalAvOption);
            $set('total_options', $totalOptions);
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


    public static function getBasicItemsField()
    {
        return [
            Forms\Components\TextInput::make('title')
                ->label('Titre élement')
                ->required()
                ->live(onBlur:true)
                ->columnSpanFull(),
            Forms\Components\MarkdownEditor::make('description')
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
                Actions\Action::make('activate_v')
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
            'index' => Pages\ListQuotes::route('/'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
            'preview-pdf' => Pages\PreviewPdf::route('/{record}/preview-pdf'),
        ];
    }
}
