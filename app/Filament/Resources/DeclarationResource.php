<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeclarationResource\Pages;
use App\Models\Declaration;
use App\Services\Declarations\DeclarationCalculator;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeclarationResource extends Resource
{
    protected static ?string $model = Declaration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?string $modelLabel = 'Déclaration';

    protected static ?string $pluralModelLabel = 'Déclarations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Période déclarée')
                    ->description('La prochaine période non couverte est calculée automatiquement à partir de la dernière déclaration enregistrée.')
                    ->schema([
                        Select::make('type')
                            ->label('Déclaration')
                            ->options(Declaration::typeOptions())
                            ->default(Declaration::TYPE_VAT)
                            ->required()
                            ->live()
                            ->disabledOn('edit')
                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::fillPreview(
                                $state,
                                $set,
                                $get('calculation_mode') ?? Declaration::MODE_AUTOMATIC,
                            )),

                        Select::make('calculation_mode')
                            ->label('Mode de calcul')
                            ->options(Declaration::modeOptions())
                            ->default(Declaration::MODE_AUTOMATIC)
                            ->required()
                            ->live()
                            ->disabledOn('edit')
                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::fillPreview($get('type'), $set, $state)),

                        DatePicker::make('period_start')
                            ->label('Du')
                            ->readOnly()
                            ->dehydrated(false),

                        DatePicker::make('period_end')
                            ->label('Au')
                            ->readOnly()
                            ->dehydrated(false),

                        TextInput::make('covered_months_display')
                            ->label('Mois couverts')
                            ->readOnly()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (TextInput $component, ?Declaration $record): void {
                                if ($record) {
                                    $component->state(implode(', ', $record->covered_months ?? []));
                                }
                            }),
                    ])
                    ->columns(4),

                Section::make('Montants de la déclaration')
                    ->description('En automatique, les montants viennent des factures synchronisées. En manuel, ils sont saisis librement.')
                    ->schema([
                        TextInput::make('client_invoice_count')
                            ->label('Factures clients retenues')
                            ->integer()
                            ->readOnly()
                            ->dehydrated(false)
                            ->visible(fn (callable $get): bool => $get('calculation_mode') === Declaration::MODE_AUTOMATIC)
                            ->afterStateHydrated(function (TextInput $component, ?Declaration $record): void {
                                if ($record) {
                                    $component->state(count($record->calculation_details['client_invoice_ids'] ?? []));
                                }
                            }),

                        TextInput::make('supplier_invoice_count')
                            ->label('Factures fournisseurs Qonto retenues')
                            ->integer()
                            ->readOnly()
                            ->dehydrated(false)
                            ->visible(fn (callable $get): bool => $get('type') === Declaration::TYPE_VAT && $get('calculation_mode') === Declaration::MODE_AUTOMATIC)
                            ->afterStateHydrated(function (TextInput $component, ?Declaration $record): void {
                                if ($record) {
                                    $component->state(count($record->calculation_details['qonto_supplier_invoice_ids'] ?? []));
                                }

                            }),
                        TextInput::make('turnover_excluding_tax')
                            ->label('Chiffre d’affaires HT à déclarer')
                            ->numeric()
                            ->minValue(0)
                            ->required(fn (callable $get): bool => $get('calculation_mode') === Declaration::MODE_MANUAL && $get('type') === Declaration::TYPE_URSSAF)
                            ->suffix('EUR')
                            ->readOnly(fn (callable $get): bool => $get('calculation_mode') === Declaration::MODE_AUTOMATIC)
                            ->dehydrated()
                            ->visible(fn (callable $get): bool => $get('type') === Declaration::TYPE_URSSAF),

                        TextInput::make('vat_collected')
                            ->label('TVA collectée')
                            ->numeric()
                            ->live(debounce: 400)
                            ->minValue(0)
                            ->required(fn (callable $get): bool => $get('calculation_mode') === Declaration::MODE_MANUAL && $get('type') === Declaration::TYPE_VAT)
                            ->suffix('EUR')
                            ->readOnly(fn (callable $get): bool => $get('calculation_mode') === Declaration::MODE_AUTOMATIC)
                            ->dehydrated()
                            ->afterStateUpdated(fn (callable $set, callable $get) => self::updateVatBalance($set, $get))
                            ->visible(fn (callable $get): bool => $get('type') === Declaration::TYPE_VAT),

                        TextInput::make('vat_deductible')
                            ->label('TVA déductible')
                            ->numeric()
                            ->live(debounce: 400)
                            ->minValue(0)
                            ->required(fn (callable $get): bool => $get('calculation_mode') === Declaration::MODE_MANUAL && $get('type') === Declaration::TYPE_VAT)
                            ->suffix('EUR')
                            ->readOnly(fn (callable $get): bool => $get('calculation_mode') === Declaration::MODE_AUTOMATIC)
                            ->dehydrated()
                            ->afterStateUpdated(fn (callable $set, callable $get) => self::updateVatBalance($set, $get))
                            ->visible(fn (callable $get): bool => $get('type') === Declaration::TYPE_VAT),

                        TextInput::make('previous_vat_credit')
                            ->label('Crédit de TVA reporté')
                            ->helperText('Crédit disponible à la fin de la déclaration de TVA précédente.')
                            ->numeric()
                            ->suffix('EUR')
                            ->readOnly()
                            ->dehydrated(false)
                            ->visible(fn (callable $get): bool => $get('type') === Declaration::TYPE_VAT),

                        TextInput::make('vat_due')
                            ->label('TVA à décaisser')
                            ->numeric()
                            ->suffix('EUR')
                            ->readOnly()
                            ->dehydrated(false)
                            ->visible(fn (callable $get): bool => $get('type') === Declaration::TYPE_VAT),

                        TextInput::make('vat_credit')
                            ->label('Crédit de TVA')
                            ->numeric()
                            ->suffix('EUR')
                            ->readOnly()
                            ->dehydrated(false)
                            ->visible(fn (callable $get): bool => $get('type') === Declaration::TYPE_VAT),
                    ])
                    ->columns(3),

                Section::make('Suivi')
                    ->schema([
                        Select::make('status')
                            ->label('État')
                            ->options(Declaration::statusOptions())
                            ->default('draft')
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period_start', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => Declaration::typeOptions()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('calculation_mode')
                    ->label('Mode')
                    ->formatStateUsing(fn (string $state): string => Declaration::modeOptions()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('covered_months')
                    ->label('Mois couverts')
                    ->state(fn (Declaration $record): string => implode(', ', $record->covered_months ?? [])),
                TextColumn::make('period_start')->label('Du')->date('d/m/Y')->sortable(),
                TextColumn::make('period_end')->label('Au')->date('d/m/Y')->sortable(),
                TextColumn::make('turnover_excluding_tax')
                    ->label('CA HT')
                    ->money('EUR'),
                TextColumn::make('vat_collected')->label('TVA collectée')->money('EUR'),
                TextColumn::make('vat_deductible')->label('TVA déductible')->money('EUR'),
                TextColumn::make('vat_due')->label('À décaisser')->money('EUR'),
                TextColumn::make('status')
                    ->label('État')
                    ->formatStateUsing(fn (string $state): string => Declaration::statusOptions()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (Declaration $record): bool => $record->status === 'draft'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('creator');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeclarations::route('/'),
            'create' => Pages\CreateDeclaration::route('/create'),
            'edit' => Pages\EditDeclaration::route('/{record}/edit'),
        ];
    }

    private static function fillPreview(?string $type, callable $set, ?string $mode = Declaration::MODE_AUTOMATIC): void
    {
        if (! array_key_exists((string) $type, Declaration::typeOptions())) {
            return;
        }

        $preview = app(DeclarationCalculator::class)->next($type);
        $previousVatCreditCents = app(DeclarationCalculator::class)
            ->previousVatCreditCents($preview['period_start']);

        $set('period_start', $preview['period_start']);
        $set('period_end', $preview['period_end']);
        $set('covered_months_display', implode(', ', $preview['covered_months']));
        $set('previous_vat_credit', $previousVatCreditCents / 100);

        if ($mode === Declaration::MODE_MANUAL) {
            $set('client_invoice_count', null);
            $set('supplier_invoice_count', null);
            $set('turnover_excluding_tax', null);
            $set('vat_collected', null);
            $set('vat_deductible', null);
            $set('vat_due', null);
            $set('vat_credit', null);

            return;
        }
        $set('client_invoice_count', count($preview['calculation_details']['client_invoice_ids'] ?? []));
        $set('supplier_invoice_count', count($preview['calculation_details']['qonto_supplier_invoice_ids'] ?? []));
        $set('turnover_excluding_tax', $preview['turnover_excluding_tax_cents'] / 100);
        $set('vat_collected', $preview['vat_collected_cents'] / 100);
        $set('vat_deductible', $preview['vat_deductible_cents'] / 100);
        $set('vat_due', $preview['vat_due_cents'] / 100);
        $set('vat_credit', max(0,
            $preview['vat_deductible_cents'] + $previousVatCreditCents - $preview['vat_collected_cents'],
        ) / 100);
    }

    private static function updateVatBalance(callable $set, callable $get): void
    {
        $collected = (float) ($get('vat_collected') ?? 0);
        $deductible = (float) ($get('vat_deductible') ?? 0);
        $previousCredit = (float) ($get('previous_vat_credit') ?? 0);
        $balance = $collected - $deductible - $previousCredit;

        $set('vat_due', round(max(0, $balance), 2));
        $set('vat_credit', round(max(0, -$balance), 2));
    }
}
