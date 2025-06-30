<?php

namespace App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\ExportAction;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Exports\CompanyProductsExporter;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

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
                AttachAction::make()->recordTitleAttribute('title')->preloadRecordSelect()->form(fn(AttachAction $action): array => [
                    $action->getRecordSelect(),
                    Forms\Components\TextInput::make('unit_price')->required(),
                ]), // permet d’ajouter un lien produit ↔ société
                ExportAction::make('exportClientProducts')
                    ->label('Exporter les produits associés')
                    ->exporter(CompanyProductsExporter::class)
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
