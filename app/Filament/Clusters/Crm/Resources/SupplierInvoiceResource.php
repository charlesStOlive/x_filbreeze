<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Supplier;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Filament\Clusters\Crm;
use Illuminate\Support\Carbon;
use App\Models\SupplierInvoice;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Collection;
use A909M\FilamentStateFusion\Tables\Filters\StateFusionSelectFilter;
use A909M\FilamentStateFusion\Actions\StateFusionBulkAction;
use App\Models\States\SupplierInvoice\Draft;
use App\Models\States\SupplierInvoice\Validated;
use App\Filament\Components\Tables\DateColumn;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\RelationManagers;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\EditSupplierInvoice;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\ListSupplierInvoices;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\CreateSupplierInvoice;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\CreatSupplieFromFileV2;


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
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'total_ht')),

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
                            ->afterStateUpdated(fn(callable $set, callable $get) => self::calculateTVA($set, $get, 'total_ttc')),
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
                    ->downloadable(),

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

                TextColumn::make('state')
                    ->label('État')
                    ->badge(),

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
                StateFusionSelectFilter::make('state')
                    ->multiple()
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn($record) => !($record->state instanceof Validated)),
            ])
            ->toolbarActions([
                BulkAction::make('delete')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $deleted = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            if ($record->state instanceof Validated) {
                                $skipped++;
                                continue;
                            }
                            $record->delete();
                            $deleted++;
                        }

                        if ($deleted > 0) {
                            Notification::make()
                                ->title('Suppression effectuée')
                                ->body("$deleted facture(s) supprimée(s)" . ($skipped > 0 ? ", $skipped validée(s) ignorée(s)" : ''))
                                ->success()
                                ->send();
                        } elseif ($skipped > 0) {
                            Notification::make()
                                ->title('Suppression impossible')
                                ->body("$skipped facture(s) validée(s) ne peuvent pas être supprimées")
                                ->warning()
                                ->send();
                        }
                    }),

                // BulkAction pour valider (utilise la transition DraftToValidated avec sa logique)
                StateFusionBulkAction::make('validate')
                    ->label('Valider la sélection')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->transition(Draft::class, Validated::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierInvoices::route('/'),
            'create' => CreateSupplierInvoice::route('/create'),
            'edit' => EditSupplierInvoice::route('/{record}/edit'),
            'createfromfile' => CreatSupplieFromFileV2::route('/createfromfile'),
        ];
    }
}
