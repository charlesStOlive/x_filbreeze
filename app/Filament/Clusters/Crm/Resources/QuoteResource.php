<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Quote;
use App\Models\Contact;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Filament\Forms\Components\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Filament\Clusters\Crm\Resources\QuoteResource\RelationManagers;
use Filament\Tables\Actions\CreateAction;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = Crm::class;


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('company.title')
                    ->sortable()
                    ->searchable(['title']),
                Tables\Columns\TextColumn::make('contact.full_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_retained')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version')
                    ->searchable(),
                Tables\Columns\TextColumn::make('end_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('validated_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_ht')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            Forms\Components\Select::make('contact_id')
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
            Forms\Components\Select::make('company_id')
                ->label('Client')
                ->relationship('company', 'title')
                ->searchable()
                ->required()
                ->reactive()
                ->disabled(!$companyEditable),

        ];
    }

    public static function getItemsBuilderComponent(): array
    {
        return [
            Forms\Components\Fieldset::make('main_quote')
                ->schema([

                    Forms\Components\MarkdownEditor::make('description')
                        ->label('Description du devis')
                        ->columnSpanFull(),
                    Builder::make('items')
                        ->label('Éléments')
                        ->collapsed()
                        ->live()
                        ->cloneable()
                        ->afterStateUpdated(fn(callable $set, callable $get) => self::updateItemsTotal($set, $get))
                        ->blocks([
                            Builder\Block::make('forfait')
                                ->label(function (?array $state): string {
                                    if ($state === null) {
                                        return 'Forfait';
                                    }
                                    return sprintf('%s %s (%s €HT)', 'Forfait : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
                                })
                                ->schema([
                                    ...self::getBasicItemsField(),
                                    Forms\Components\TextInput::make('total')
                                        ->label('Total TTC')
                                        ->numeric()
                                        ->live(onBlur: true)
                                        // ->afterStateUpdated(fn(callable $set, callable $get) => self::updateTotal($set, $get)),
                                ])
                                ->columns(3),
                            Builder\Block::make('tasks')
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
                                        ->afterStateUpdated(fn(callable $set, callable $get) => self::updateTaskTotal($set, $get)),
                                    Forms\Components\TextInput::make('qty')
                                        ->label('Qty')
                                        ->numeric()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(callable $set, callable $get) => self::updateTaskTotal($set, $get)),
                                    Forms\Components\TextInput::make('total')
                                        ->label('Total TTC')
                                        ->numeric()
                                        ->disabled() // Champ total est calculé automatiquement
                                        ->dehydrated(),
                                ])
                                ->columns(3),
                            Builder\Block::make('remise')
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
                                        // ->afterStateUpdated(fn(callable $set, callable $get) => self::updateTotal($set, $get)),
                                ])
                                ->columns(2)
                        ])
                        ->columnSpanFull(),
                ])
        ];
    }

    public static function updateItemsTotal(callable $set, callable $get)
    {
        // Récupère tous les éléments du parent
        $items = $get('items') ?? [];
        \Log::info('Données items', ['items' => $items]);

        // Séparer les éléments par type
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
        $set('total_ht_br', $totalHtBr);

        // Calculer et mettre à jour total_ht
        $totalHt = $totalHtBr - $totalRemise;
        $set('total_ht', $totalHt);
    }


    public static function updateTaskTotal(callable $set, callable $get)
    {
        // Récupérer les valeurs de cu et qty
        $cu = $get('cu') ?? 0;
        $qty = $get('qty') ?? 0;

        // Calculer le total pour ce bloc
        $total = $cu * $qty;

        // Mettre à jour le champ total
        $set('total', $total);

        // Appeler la mise à jour globale du total_ht
        self::updateTotal($set, $get);
    }


    public static function getBasicItemsField()
    {
        return [
            Forms\Components\TextInput::make('title')
                ->label('Titre élement')
                ->required()
                ->reactive()
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
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('activate_v')
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
        ];
    }
}
