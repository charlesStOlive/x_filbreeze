<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Filament\Clusters\Crm\Resources\SectorResource\Pages\ListSectors;
use App\Filament\Clusters\Crm\Resources\SectorResource\Pages\CreateSector;
use App\Filament\Clusters\Crm\Resources\SectorResource\Pages\EditSector;
use Filament\Forms;
use Filament\Tables;
use App\Models\Sector;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\Crm\Resources\SectorResource\Pages;
use App\Filament\Clusters\Crm\Resources\SectorResource\RelationManagers;
use App\Filament\Clusters\Crm\Resources\SectorResource\Widgets\SectorWidget;

class SectorResource extends Resource
{
    protected static ?string $model = Sector::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-s-building-library';

    protected static ?string $cluster = Crm::class;

    public static function getLabel(): string
    {
        return 'Secteurs';
    }

    public static function getWidgets(): array
    {
        return [
            SectorWidget::class,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Textarea::make('contenu')
                    ->columnSpanFull(),
                Textarea::make('txt_intro')
                    ->columnSpanFull(),
                Textarea::make('txt_kpi')
                    ->columnSpanFull(),
                TextInput::make('parent_id')
                    ->required()
                    ->numeric()
                    ->default(-1),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
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
            'index' => ListSectors::route('/'),
            'create' => CreateSector::route('/create'),
            'edit' => EditSector::route('/{record}/edit'),
        ];
    }
}
