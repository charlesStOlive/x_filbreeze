<?php

namespace App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use App\Models\Product;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;

use App\Services\MaatExports\Templates\Company\CompanyProductsExporter;
use App\Services\MaatImports\Templates\Company\CompanyProductsImporter;
use App\Services\MaatExports\Filament\Tables\ExportMaatExcelTableAction;
use App\Services\MaatImports\Filament\Tables\ImportMaatExcelTableAction;


class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';
    protected static ?string $title = 'Grilles produits';
    protected bool $allowsDuplicates = false;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Produit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.unit_price')
                    ->label('Prix (€)')
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordTitleAttribute('title')
                    ->preloadRecordSelect(false) // désactivé car on remplace le select
                    ->form(function (): array {
                        return [
                            Select::make('recordId') // nom obligatoire : recordId
                                ->label('Produit')
                                ->searchable()
                                ->options(function () {
                                    return Product::with('gamme')
                                        ->get()
                                        ->groupBy(fn($product) => $product->gamme?->name ?? 'Sans gamme')
                                        ->mapWithKeys(function ($group, $groupName) {
                                            return [
                                                $groupName => $group->mapWithKeys(fn($product) => [
                                                    $product->id => "{$product->title} ({$product->code})",
                                                ])->toArray(),
                                            ];
                                        })
                                        ->toArray();
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state && $product = Product::find($state)) {
                                        $set('unit_price', $product->unit_price);
                                    }
                                }),

                            Forms\Components\TextInput::make('unit_price')
                                ->numeric()
                                ->required(),
                        ];
                    }), // permet d’ajouter un lien produit ↔ société
                ExportMaatExcelTableAction::make('exportProduits')
                    ->label('Exporter les produits')
                    ->exporter(CompanyProductsExporter::class)
                    ->withRecord($this->getOwnerRecord()),    
                ImportMaatExcelTableAction::make('importproduct')
                    ->label('Importer les produits')
                    ->importer(CompanyProductsImporter::class)
                    ->withRecord($this->getOwnerRecord())
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('unit_price')
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                    ])
                    ->mountUsing(fn($record, $form) => $form->fill($record->pivot->toArray())),
                DetachAction::make(), // supprime la liaison pivot
            ])->defaultSort('title');
    }
}
