<?php

namespace App\Filament\Clusters\DataSets\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Gamme;
use App\Models\Product;
use Filament\Forms\Form;
use App\Enums\ProductType;
use Filament\Tables\Table;
use App\Imports\ProductImporter;
use Filament\Resources\Resource;
use App\Filament\Clusters\DataSets;
use Filament\Tables\Grouping\Group;
use YOS\FilamentExcel\Actions\Import;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Clusters\DataSets\Resources\ProductResource\Pages;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $cluster = DataSets::class;

    public static function getLabel(): string
    {
        return 'Produits';
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->required()
                    ->options(ProductType::options())
                    ->native(false),

                Forms\Components\Select::make('gamme_id')
                    ->label('Gamme')
                    ->relationship('gamme', 'name')
                    ->preload()
                    ->searchable()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom de la gamme')
                            ->required()
                            ->unique(table: 'datasets_gammes', column: 'name')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('slug', str($state)->slug());
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->helperText('Généré automatiquement depuis le nom')
                            ->unique(table: 'datasets_gammes', column: 'slug')
                    ])
                    ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                        return $action
                            ->modalHeading('Créer une nouvelle gamme')
                            ->modalSubmitActionLabel('Créer')
                            ->closeModalByClickingAway(false);
                    })
                    ->required()
                    ->native(false),

                Forms\Components\TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->groups([
                Group::make('gamme.name')
                    ->label('Gamme'),
                Group::make('type')
                    ->label('Type')
                    ->getTitleFromRecordUsing(fn($record) => $record->type?->label()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn(ProductType $state) => $state->label()),

                Tables\Columns\TextColumn::make('gamme.name')
                    ->label('Gamme')
                    ->sortable(),

                Tables\Columns\TextInputColumn::make('unit_price')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ProductType::options()),

                SelectFilter::make('gamme_id')
                    ->label('Gamme')
                    ->relationship('gamme', 'name'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                // Tables\Actions\ExportBulkAction::make()
                //     ->exporter(ProductExporter::class),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
