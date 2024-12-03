<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Sector;
use App\Models\Company;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\Crm\Resources\CompanyResource\Pages;
use App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $cluster = Crm::class;

    public static function getLabel(): string
    {
        return 'Clients';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Split::make([
                    Forms\Components\Section::make([
                        Forms\Components\Fieldset::make('Informations générales')
                            ->schema([
                                Forms\Components\Toggle::make('is_ex')->columnSpanFull(),
                                Forms\Components\TextInput::make('title')->label('Nom entreprise')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        $set('slug', Str::slug($state));
                                    })
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('sector_id')
                                    ->relationship(name: 'sector', titleAttribute: 'title')->options(Sector::selectArrayNested()),
                                Forms\Components\TextInput::make('nb_collab')
                                    ->numeric()
                                    ->default(10),
                            ])
                            ->columns([
                                'sm' => 1, // Mobile: 1 colonne
                                'md' => 2, // Écran normal: 4 colonnes
                            ]),
                        Forms\Components\Fieldset::make('Localisation')
                            ->schema([
                                Forms\Components\Textarea::make('address')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('city')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('tel')
                                    ->tel()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('longitude')
                                    ->numeric(),
                                Forms\Components\TextInput::make('latitude')
                                    ->numeric(),
                                Forms\Components\TextInput::make('distance')
                                    ->numeric(),
                                Forms\Components\TextInput::make('country_id')
                                    ->numeric(),
                            ])
                            ->columns([
                                'sm' => 1,
                                'md' => 4,
                            ]),
                        Forms\Components\Fieldset::make('Paramètres et autres')
                            ->schema([
                                Forms\Components\TextInput::make('siret')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('others')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('memo')
                                    ->columnSpanFull(),
                            ])
                            ->columns([
                                'sm' => 1,
                                'md' => 4,
                            ]),
                    ])->compact(),
                    Forms\Components\Section::make([
                        Forms\Components\Fieldset::make('Contact')
                            ->schema([
                                Forms\Components\TextInput::make('site_url')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                            ])
                            ->columns(1),
                        Forms\Components\Fieldset::make('style')
                            ->schema([
                                Forms\Components\ColorPicker::make('primary_color'),
                                Forms\Components\ColorPicker::make('secondary_color'),
                            ])
                            ->columns(1),
                    ])->grow(false)->compact(),
                ])->from('md')->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('primary_color')
                    ->searchable(),
                Tables\Columns\TextColumn::make('secondary_color')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sector_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_ex')
                    ->boolean(),
                Tables\Columns\TextColumn::make('nb_collab')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tel')
                    ->searchable(),
                Tables\Columns\TextColumn::make('site_url')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('siret')
                    ->searchable(),
                Tables\Columns\TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('distance')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('others')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
