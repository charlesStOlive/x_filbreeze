<?php

namespace App\Filament\Clusters\DataSets\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Product;
use Filament\Forms\Form;
use App\Enums\ProductType;
use Filament\Tables\Table;
use App\Imports\ProductImporter;
use Filament\Resources\Resource;
use App\Filament\Clusters\DataSets;
use YOS\FilamentExcel\Actions\Import;
use App\Services\Exports\ProductExporter;
use Filament\Tables\Filters\SelectFilter;
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
                    ->options(\App\Enums\ProductType::options())
                    ->native(false),

                Forms\Components\TextInput::make('gamme')
                    ->maxLength(100),

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
                    ->formatStateUsing(fn(\App\Enums\ProductType $state) => $state->label()),

                Tables\Columns\TextColumn::make('gamme')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(\App\Enums\ProductType::options()),

                Tables\Filters\SelectFilter::make('gamme')
                    ->options(
                        \App\Models\Product::query()
                            ->distinct()
                            ->pluck('gamme', 'gamme')
                            ->filter()
                            ->toArray()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\ExportBulkAction::make()
                    ->exporter(ProductExporter::class)
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
