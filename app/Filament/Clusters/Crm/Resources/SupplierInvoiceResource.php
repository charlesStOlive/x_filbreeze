<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Supplier;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use Illuminate\Support\Carbon;
use App\Models\SupplierInvoice;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\RelationManagers;


class SupplierInvoiceResource extends Resource
{
    protected static ?string $model = SupplierInvoice::class;

    protected static ?string $cluster = Crm::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getLabel(): string
    {
        return 'Factures fournisseurs';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Les autres composants du formulaire restent inchangés
                Forms\Components\Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label('Supplier')
                    ->required(),

                Forms\Components\TextInput::make('invoice_number')
                    ->label('Invoice Number'),

                Forms\Components\DatePicker::make('invoice_at')
                    ->label('Invoice Date')
                    ->required()
                    ->default(today()),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'validated' => 'Validated',
                    ])
                    ->default('pending')
                    ->label('Status')
                    ->required()
                    ->columnSpan('full'),

                Forms\Components\Section::make('Détails TVA')
                    ->schema([
                        Forms\Components\Toggle::make('has_tva')
                            ->label('Has TVA')
                            ->default(true)
                            ->columnSpan('full')
                            ->live(debounce: 1000)
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'has_tva')),

                        Forms\Components\TextInput::make('total_ht')
                            ->numeric()
                            ->label('Total HT')
                            ->suffix('€ HT')
                            ->live(debounce: 1000)
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'total_ht'))
                            ->requiredIf('status', 'validated'),

                        Forms\Components\TextInput::make('tx_tva')
                            ->numeric()
                            ->nullable()
                            ->default('20')
                            ->label('Tx TVA')
                            ->suffix('%')
                            ->live(debounce: 1000)
                            ->visible(fn(callable $get) => $get('has_tva'))
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'tx_tva'))
                            ->requiredIf('has_tva', true),

                        Forms\Components\TextInput::make('tva')
                            ->numeric()
                            ->label('Total TVA')
                            ->suffix('€')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn(callable $get) => $get('has_tva'))
                            ->requiredIf('has_tva', true),

                        Forms\Components\TextInput::make('total_ttc')
                            ->numeric()
                            ->label('Total TTC')
                            ->suffix('€ TTC')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'total_ttc'))
                            ->requiredIf('status', 'validated'),
                    ])
                    ->columns([
                        'sm' => 1,
                        'lg' => 4,
                    ])
                    ->columnSpan('full'),
                SpatieMediaLibraryFileUpload::make('invoice')
                    ->collection('invoice')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf'])
                    ->openable()
                    ->downloadable()
                    ->required(fn(callable $get) => $get('status') === 'validated'),

                Forms\Components\Textarea::make('notes')
                    ->nullable()
                    ->label('Notes')
                    ->rows(4)
                    ->extraAttributes(['style' => 'background-color: #fff9c4;'])
                    ->columnSpan(2),




            ])
            ->columns(3);
    }

    private static function calculateTVA(callable $set, callable $get, string $changedField)
    {
        if ($get('has_tva')) {
            $tvaRate = (float) $get('tx_tva') ?? 0;
            $totalHT = (float) $get('total_ht') ?? 0;
            $totalTTC = (float) $get('total_ttc') ?? 0;

            switch ($changedField) {
                case 'total_ht':
                    $tva = round($totalHT * ($tvaRate / 100), 2);
                    $set('tva', $tva);
                    $set('total_ttc', round($totalHT + $tva, 2));
                    break;

                case 'total_ttc':
                    $tva = round($totalTTC * ($tvaRate / (100 + $tvaRate)), 2);
                    $set('tva', $tva);
                    $set('total_ht', round($totalTTC - $tva, 2));
                    break;

                case 'tx_tva':
                    $tva = round($totalHT * ($tvaRate / 100), 2);
                    $set('tva', $tva);
                    $set('total_ttc', round($totalHT + $tva, 2));
                    break;

                case 'has_tva':
                    if (!$get('has_tva')) {
                        $set('tva', null);
                        $set('tx_tva', null);
                        $set('total_ttc', $totalHT);
                    } else {
                        // Si has_tva est activé, recalculer en fonction de tx_tva
                        $tva = round($totalHT * ($tvaRate / 100), 2);
                        $set('tva', $tva);
                        $set('total_ttc', round($totalHT + $tva, 2));
                    }
                    break;
            }
        } else {
            $set('tva', null);
            $set('tx_tva', null);
            $set('total_ttc', $get('total_ht'));
        }
    }

    


    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('supplier.name')
                    ->label('Fournisseur'),
                Group::make('invoice_at_my')
                    ->label('Annes Mois'),
                Group::make('invoice_at_qy')
                    ->label('Semestre Mois'),

            ])
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'pending' => 'secondary',
                        'validated' => 'success',
                    ]),

                Tables\Columns\TextColumn::make('total_ttc')
                    ->summarize(Sum::make())
                    ->label('Total TTC')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tva')
                    ->summarize(Sum::make())
                    ->label('Total TVA')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_ht')
                    ->label('Total HT')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Masquer par défaut

                Tables\Columns\TextColumn::make('invoice_at')
                    ->label('Invoice Date')
                    ->dateTime('d/m/Y')
                    ->sortable(),


            ])
            ->defaultSort('invoice_at', 'desc')
            ->filters([
                // Add any filters if necessary
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])->bulkActions([
                BulkAction::make('delete')
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierInvoices::route('/'),
            'create' => Pages\CreateSupplierInvoice::route('/create'),
            'edit' => Pages\EditSupplierInvoice::route('/{record}/edit'),
            'createfromfile' => Pages\CreatSupplieFromFile::route('/createfromfile'),
        ];
    }
}
