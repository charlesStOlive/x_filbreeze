<?php

namespace App\Filament\Clusters\Crm\Resources;

use App\Models\Supplier;
use Filament\Forms\Form;
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

    protected static ?string $navigationIcon = 'heroicon-s-building-storefront';

    public static function getLabel(): string
    {
        return 'Fournisseurs';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Fournisseur')
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
                            ->unique(table: 'suppliers', column: 'incoming_email') // Assure l'unicité 
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

                Forms\Components\Section::make('Adresse')
                    ->schema([
                        TextInput::make('phone')
                            ->tel()
                            ->label('Phone Number'),

                        TextInput::make('address')
                            ->label('Address'),

                        TextInput::make('city')
                            ->label('City'),

                        TextInput::make('country')
                            ->label('Country'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Memo')
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
                TextColumn::make('email')->sortable()->searchable()->label('Email'),
                TextColumn::make('phone')->label('Phone'),
                TextColumn::make('city')->label('City'),
                TextColumn::make('country')->label('Country'),
            ])
            ->filters([
                // Add any filters if necessary
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
