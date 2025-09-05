<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\PermissionResource\Pages\ListPermissions;
use App\Filament\Resources\PermissionResource\Pages\CreatePermission;
use App\Filament\Resources\PermissionResource\Pages\EditPermission;
use App\Filament\Resources\PermissionResource\Pages;
use App\Services\PermissionService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';

    protected static string | \UnitEnum | null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?string $pluralModelLabel = 'Permissions';

    protected static ?string $modelLabel = 'Permission';

    public static function canViewAny(): bool
    {
        return PermissionService::can('permissions.view');
    }

    public static function canCreate(): bool
    {
        return PermissionService::can('permissions.create');
    }

    public static function canEdit($record): bool
    {
        return PermissionService::can('permissions.edit');
    }

    public static function canDelete($record): bool
    {
        return PermissionService::can('permissions.delete');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('guard_name')
                    ->label('Guard')
                    ->default('web')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
            'create' => CreatePermission::route('/create'),
            'edit' => EditPermission::route('/{record}/edit'),
        ];
    }
}
