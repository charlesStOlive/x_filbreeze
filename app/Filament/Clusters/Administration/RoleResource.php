<?php

namespace App\Filament\Clusters\Administration;

use CharlesStOlive\FilamentPermissionManager\Support\SuperAdmin;
use App\Filament\Clusters\Administration;
use App\Filament\Clusters\Administration\RoleResource\Pages;
use App\Filament\Clusters\Administration\RoleResource\Schemas\RoleForm;
use App\Filament\Clusters\Administration\RoleResource\Tables\RolesTable;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;
use CharlesStOlive\FilamentPermissionManager\Traits\HasFilamentAuthorization;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use HasFilamentAuthorization;

    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $cluster = Administration::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Rôles';

    protected static ?string $pluralModelLabel = 'Rôles';

    protected static ?string $modelLabel = 'Rôle';



    public static function canEdit($record): bool
    {
        // Empêcher l'édition du rôle Super Admin par des non-Super Admin
        if ($record && SuperAdmin::isRole($record)) {
            return SuperAdmin::is(auth()->user()) && PermissionService::can('administration.role.edit');
        }

        return PermissionService::can('administration.role.edit');
    }

    public static function canDelete($record): bool
    {
        // Empêcher la suppression du rôle Super Admin
        if ($record && SuperAdmin::isRole($record)) {
            return false;
        }

        // Vérifier si le rôle est utilisé par des utilisateurs
        if ($record && $record->users()->count() > 0) {
            return false; // Empêcher la suppression si utilisé
        }

        return PermissionService::can('administration.role.delete');
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
