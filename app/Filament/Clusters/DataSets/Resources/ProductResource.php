<?php

namespace App\Filament\Clusters\DataSets\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Clusters\DataSets\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Clusters\DataSets\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Clusters\DataSets\Resources\ProductResource\Pages\EditProduct;
use Filament\Forms;
use Filament\Tables;
use App\Models\Gamme;
use App\Models\Product;
use App\Enums\ProductType;
use Filament\Tables\Table;
use App\Imports\ProductImporter;
use Filament\Resources\Resource;
use App\Filament\Clusters\DataSets;
use Filament\Tables\Grouping\Group;
use YOS\FilamentExcel\Actions\Import;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Clusters\DataSets\Resources\ProductResource\Pages;
use CharlesStOlive\FilamentPermissionManager\Traits\HasFilamentAuthorization;

class ProductResource extends Resource
{
    use HasFilamentAuthorization;

    protected static ?string $model = Product::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $cluster = DataSets::class;

    public static function getLabel(): string
    {
        return 'Produits';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),

                TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->required()
                    ->options(ProductType::options())
                    ->native(false),

                Select::make('gamme_id')
                    ->label('Gamme')
                    ->relationship('gamme', 'name')
                    ->preload()
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nom de la gamme')
                            ->required()
                            ->unique(table: 'datasets_gammes', column: 'name')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('slug', str($state)->slug());
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->helperText('Généré automatiquement depuis le nom')
                            ->unique(table: 'datasets_gammes', column: 'slug')
                    ])
                    ->createOptionAction(function (Action $action) {
                        return $action
                            ->modalHeading('Créer une nouvelle gamme')
                            ->modalSubmitActionLabel('Créer')
                            ->closeModalByClickingAway(false);
                    })
                    ->required()
                    ->native(false),

                TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('gamme.name')
                    ->label('Gamme'),
                Group::make('type')
                    ->label('Type')
                    ->getTitleFromRecordUsing(fn($record) => $record->type?->label()),
            ])
            ->defaultGroup('gamme.name')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn(ProductType $state) => $state->label()),

                TextColumn::make('gamme.name')
                    ->label('Gamme')
                    ->sortable(),

                TextInputColumn::make('unit_price')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ProductType::options()),

                SelectFilter::make('gamme_id')
                    ->label('Gamme')
                    ->relationship('gamme', 'name'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
                // Tables\Actions\ExportBulkAction::make()
                //     ->exporter(ProductExporter::class),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
