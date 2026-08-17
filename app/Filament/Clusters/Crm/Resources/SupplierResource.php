<?php

namespace App\Filament\Clusters\Crm\Resources;

use CharlesStOlive\FilamentQonto\Filament\RelationManagers\QontoSupplierInvoicesRelationManager;
use CharlesStOlive\FilamentQonto\Filament\RelationManagers\QontoTransactionsRelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Filament\Clusters\Crm\Resources\SupplierResource\Pages\ListSuppliers;
use App\Filament\Clusters\Crm\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Clusters\Crm\Resources\SupplierResource\Pages\EditSupplier;
use App\Models\Supplier;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use App\Filament\Clusters\Crm\Resources\SupplierResource\Pages;

class SupplierResource extends Resource
{

    protected static ?string $model = Supplier::class;

    protected static ?string $cluster = Crm::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-s-building-storefront';

    public static function getLabel(): string
    {
        return 'Fournisseurs';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fournisseur')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->label('Supplier Name')
                            ->live(onBlur: true) // Ajoute un délai pour la génération du slug
                            ->afterStateUpdated(function (callable $set, $state) {
                                $set('slug', Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->label('Slug')
                            ->unique(table: 'crm_suppliers', column: 'slug') // Assure l'unicité du slug dans la table suppliers
                            ->hint('modifiable si besoin')
                            ->hintIcon('heroicon-s-information-circle'),

                        TextInput::make('email')
                            ->email()
                            ->nullable()
                            ->label('Email'),

                        TextInput::make('incoming_email')
                            ->email()
                            ->nullable()
                            ->unique(table: 'crm_suppliers', column: 'incoming_email') // Assure l'unicité 
                            ->label('Incoming Email'),

                        TextInput::make('incoming_email_title_filter')
                            ->email()
                            ->nullable()
                            ->hint('Permet de ne traiter ques les mails qui contiennent ce titre')
                            ->hintIcon('heroicon-s-information-circle')
                            ->label('Filtre titre email de facture')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Facturation / Qonto')
                    ->schema([
                        TextInput::make('legal_name')
                            ->label('Nom légal'),

                        TextInput::make('qonto_supplier_id')
                            ->label('ID fournisseur Qonto')
                            ->helperText('Renseigné automatiquement quand Qonto fournit un identifiant fournisseur.'),

                        TextInput::make('qonto_supplier_name')
                            ->label('Nom Qonto')
                            ->disabled(),

                        TextInput::make('vat_number')
                            ->label('TVA intracom'),

                        TextInput::make('tax_identification_number')
                            ->label('TIN / identifiant fiscal'),

                        TextInput::make('siren')
                            ->label('SIREN'),

                        TextInput::make('siret')
                            ->label('SIRET'),

                        TextInput::make('iban')
                            ->label('IBAN'),

                        TextInput::make('bic')
                            ->label('BIC'),

                        TextInput::make('qonto_last_synced_at')
                            ->label('Dernière synchro Qonto')
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Adresse')
                    ->schema([
                        TextInput::make('phone')
                            ->tel()
                            ->label('Phone Number'),

                        TextInput::make('address')
                            ->label('Address'),

                        TextInput::make('city')
                            ->label('City'),

                        TextInput::make('postal_code')
                            ->label('Code postal'),

                        TextInput::make('country')
                            ->label('Country'),
                    ])
                    ->columns(2),

                Section::make('Memo')
                    ->schema([
                        Textarea::make('memo')
                            ->nullable()
                            ->label('Memo')
                            ->rows(4)
                            ->extraAttributes(['style' => 'background-color: #fff9c4;']),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable()->label('Supplier Name'),
                TextColumn::make('legal_name')->sortable()->searchable()->label('Nom légal')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('qonto_supplier_id')->label('Qonto')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vat_number')->label('TVA')->searchable()->toggleable(),
                TextColumn::make('siret')->label('SIRET')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')->sortable()->searchable()->label('Email'),
                TextColumn::make('phone')->label('Phone'),
                TextColumn::make('city')->label('City'),
                TextColumn::make('country')->label('Country'),
            ])
            ->filters([
                // Add any filters if necessary
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            QontoTransactionsRelationManager::class,
            QontoSupplierInvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
