<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkAction;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\ListSupplierInvoices;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\CreateSupplierInvoice;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\EditSupplierInvoice;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\CreatSupplieFromFile;
use Filament\Forms;
use Filament\Tables;
use App\Models\Supplier;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use Illuminate\Support\Carbon;
use App\Models\SupplierInvoice;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Grouping\Group;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Collection;
use App\Filament\Components\Tables\DateColumn;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\RelationManagers;


class SupplierInvoiceResource extends Resource
{
    protected static ?string $model = SupplierInvoice::class;

    protected static ?string $cluster = Crm::class;

    protected static string | \BackedEnum | null $navigationIcon = 'fas-receipt';

    public static function getLabel(): string
    {
        return 'Factures fournisseurs';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Les autres composants du formulaire restent inchangés
                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label('Supplier')
                    ->required(),

                TextInput::make('invoice_number')
                    ->label('Invoice Number'),

                DatePicker::make('invoice_at')
                    ->label('Invoice Date')
                    ->required()
                    ->default(today()),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'validated' => 'Validated',
                    ])
                    ->default('pending')
                    ->label('Status')
                    ->required()
                    ->columnSpan('full'),

                Section::make('Détails TVA')
                    ->schema([
                        Toggle::make('has_tva')
                            ->label('Has TVA')
                            ->default(true)
                            ->columnSpan('full')
                            ->live(debounce: 1000)
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'has_tva')),

                        TextInput::make('total_ht')
                            ->numeric()
                            ->label('Total HT')
                            ->suffix('€ HT')
                            ->live(debounce: 1000)
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'total_ht'))
                            ->requiredIf('status', 'validated'),

                        TextInput::make('tx_tva')
                            ->numeric()
                            ->nullable()
                            ->default('20')
                            ->label('Tx TVA')
                            ->suffix('%')
                            ->live(debounce: 1000)
                            ->visible(fn(callable $get) => $get('has_tva'))
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'tx_tva'))
                            ->requiredIf('has_tva', true),

                        TextInput::make('tva')
                            ->numeric()
                            ->label('Total TVA')
                            ->suffix('€')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn(callable $get) => $get('has_tva'))
                            ->requiredIf('has_tva', true),

                        TextInput::make('total_ttc')
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

                Textarea::make('notes')
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
                    ->label('Annes Mois')
                    ->orderQueryUsing(fn(Builder $query) => $query->orderBy('invoice_at_my', 'desc')),

                Group::make('invoice_at_qy')
                    ->label('Semestre Mois')
                    ->orderQueryUsing(fn(Builder $query) => $query->orderBy('invoice_at_qy', 'desc')),

            ])
            ->groupingDirectionSettingHidden()
            ->defaultGroup('invoice_at_my')
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'pending' => 'secondary',
                        'validated' => 'success',
                    ]),
                TextColumn::make('invoice_number')
                    ->label('Numéro')
                    ->searchable(),

                TextColumn::make('total_ttc')
                    ->summarize(Sum::make())
                    ->label('Total TTC')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('tva')
                    ->summarize(Sum::make())
                    ->label('Total TVA')
                    ->sortable(),

                TextColumn::make('total_ht')
                    ->label('Total HT')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Masquer par défaut

                DateColumn::make('invoice_at')
                    ->label('Invoice Date'),


            ])
            ->filters([
                // Add any filters if necessary
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])->toolbarActions([
                BulkAction::make('delete')
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierInvoices::route('/'),
            'create' => CreateSupplierInvoice::route('/create'),
            'edit' => EditSupplierInvoice::route('/{record}/edit'),
            'createfromfile' => CreatSupplieFromFile::route('/createfromfile'),
        ];
    }
}
