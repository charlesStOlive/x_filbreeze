<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Invoice;
use App\Models\Contact;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Filament\Forms\Components\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\RelationManagers;
use Filament\Tables\Actions\CreateAction;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = Crm::class;


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->sortable()
                    ->description(fn ($record): string => \Str::limit($record->title, 35))
                    ->searchable(['code', 'title']),
                Tables\Columns\TextColumn::make('company.title')
                    ->sortable()
                    ->description(fn ($record): string => $record->contact->full_name),
                Tables\Columns\TextColumn::make('submited_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payed_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_ht')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_ttc')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_tva')
                    ->icon(fn(string $state): string => match ($state) {
                        "1" => 'heroicon-o-check-circle',
                        default => 'heroicon-o-x-circle',
                    })->color(fn(string $state): string => match ($state) {
                        "1" => 'success',
                        default => 'info',
                    })

                    ->sortable(),
                Tables\Columns\TextColumn::make('tva')
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
            Forms\Components\Fieldset::make('Elements du facture')
                ->schema([
                    Builder::make('items')
                        ->label('Liste des élements')
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
                                        ->label('Total')
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
                                        ->label('Total')
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
        if ($get('has_tva')) {
            $set('tva', round($totalHt * $get('tx_tva'),2));
            $set('total_ttc', round($totalHt + $get('tva'),2));
        } else {
            $set('tva', 0);
            $set('total_ttc', round($totalHt,2));
        }
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
        self::updateItemsTotal($set, $get);
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




    public static function getRelations(): array
    {
        return [];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
            'preview-pdf' => Pages\PreviewPdf::route('/{record}/preview-pdf'),
        ];
    }
}
