<?php

namespace App\Filament\Clusters\Administration;

use CharlesStOlive\FilamentPermissionManager\Support\SuperAdmin;
use App\Filament\Clusters\Administration;
use App\Filament\Clusters\Administration\UserResource\Pages;
use App\Filament\Clusters\Administration\UserResource\Schemas\UserForm;
use App\Filament\Clusters\Administration\UserResource\Tables\UsersTable;
use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;
use CharlesStOlive\FilamentPermissionManager\Traits\HasFilamentAuthorization;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use HasFilamentAuthorization;

    protected static array $specificPermissions = [
        'api-token',
        '2fa',
    ];

    /** Leurs libellés dans l'écran des rôles : elles ouvrent deux rubriques du profil de chacun (EditProfile). */
    protected static array $permissionLabels = [
        'api-token' => 'Profil (options) : créer ses clés API',
        '2fa' => 'Profil (options) : activer la double authentification',
    ];

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $cluster = Administration::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'Utilisateur';

    public static function canEdit($record): bool
    {
        // Vérifier si l'enregistrement est un Super Admin
        if ($record && SuperAdmin::is($record)) {
            // Seul un Super Admin peut éditer un Super Admin
            return SuperAdmin::is(auth()->user()) && parent::canEdit($record);
        }

        return parent::canEdit($record);
    }

    public static function canDelete($record): bool
    {
        // Vérifier si l'enregistrement est un Super Admin
        if ($record && SuperAdmin::is($record)) {
            // Seul un Super Admin peut supprimer un Super Admin
            return SuperAdmin::is(auth()->user()) && parent::canDelete($record);
        }

        return parent::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
