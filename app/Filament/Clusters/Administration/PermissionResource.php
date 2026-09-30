<?php

namespace App\Filament\Clusters\Administration;

use App\Filament\Clusters\Administration;
use App\Filament\Clusters\Administration\PermissionResource\Pages;
use App\Filament\Clusters\Administration\PermissionResource\Schemas\PermissionForm;
use App\Filament\Clusters\Administration\PermissionResource\Tables\PermissionsTable;
use CharlesStOlive\FilamentPermissionManager\Traits\HasFilamentAuthorization;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    use HasFilamentAuthorization;

    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = Administration::class;

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?string $pluralModelLabel = 'Permissions';

    protected static ?string $modelLabel = 'Permission';

    public static function form(Schema $schema): Schema
    {
        return PermissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermissionsTable::configure($table);
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
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
