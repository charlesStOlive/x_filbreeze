<?php

namespace App\Filament\Clusters\Crm\Resources;

use App\Models\Supplier;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use App\Filament\Clusters\Crm\Resources\SupplierResource\Pages;

class SupplierResource extends Resource
{

    protected static ?string $model = Supplier::class;

    protected static ?string $cluster = Crm::class;

    public static function getNavigationLabel(): string
    {
        return __('crm.suppliers');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->required()->label('Supplier Name'),
                TextInput::make('email')->email()->required()->label('Email'),
                TextInput::make('phone')->tel()->label('Phone Number'),
                TextInput::make('address')->label('Address'),
                TextInput::make('city')->label('City'),
                TextInput::make('country')->label('Country'),
                Textarea::make('notes')->label('Notes')->rows(4),
            ]);
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
