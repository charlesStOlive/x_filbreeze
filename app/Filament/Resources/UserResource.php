<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\RelationManagers;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;
use CharlesStOlive\FilamentPermissionManager\Services\ApiTokenService;
use CharlesStOlive\FilamentPermissionManager\Traits\HasFilamentAuthorization;

class UserResource extends Resource
{
    use HasFilamentAuthorization;
    
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'Utilisateur';

    public static function canEdit($record): bool
    {
        // Vérifier si l'enregistrement est un Super Admin
        if ($record && $record->hasRole('Super Admin')) {
            // Seul un Super Admin peut éditer un Super Admin
            return auth()->user()->hasRole('Super Admin') && parent::canEdit($record);
        }
        
        return parent::canEdit($record);
    }

    public static function canDelete($record): bool
    {
        // Vérifier si l'enregistrement est un Super Admin
        if ($record && $record->hasRole('Super Admin')) {
            // Seul un Super Admin peut supprimer un Super Admin
            return auth()->user()->hasRole('Super Admin') && parent::canDelete($record);
        }
        
        return parent::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->dehydrated(fn($state) => filled($state))
                    ->minLength(8)
                    ->same('password_confirmation')
                    ->revealable()
                    ->helperText(
                        fn(string $operation): string =>
                        $operation === 'edit' ? 'Laissez vide pour conserver le mot de passe actuel' : ''
                    ),
                TextInput::make('password_confirmation')
                    ->label('Confirmer le mot de passe')
                    ->password()
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->requiredWith('password')
                    ->dehydrated(false)
                    ->minLength(8)
                    ->revealable(),

                // Avertissement pour les Super Admin
                TextEntry::make('super_admin_warning')
                    ->label('')
                    ->state('⚠️ Cet utilisateur est Super Admin et dispose de TOUS les droits via Gate::before()')
                    ->visible(fn($record) => $record && $record->hasRole('Super Admin'))
                    ->columnSpanFull(),

                Select::make('roles')
                    ->label('Rôles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable()
                    ->options(function () {
                        // Si l'utilisateur connecté est Super Admin, afficher tous les rôles
                        if (auth()->user()->hasRole('Super Admin')) {
                            return \Spatie\Permission\Models\Role::all()->pluck('name', 'id');
                        }

                        // Sinon, afficher tous les rôles sauf Super Admin
                        return \Spatie\Permission\Models\Role::where('name', '!=', 'Super Admin')
                            ->pluck('name', 'id');
                    })
                    ->disabled(function ($record) {
                        // Empêcher la modification des rôles des Super Admin par des non-Super Admin
                        if ($record && $record->hasRole('Super Admin')) {
                            return !auth()->user()->hasRole('Super Admin');
                        }
                        return false;
                    }),
                CheckboxList::make('permissions')
                    ->label('Permissions directes')
                    ->relationship('permissions', 'name')
                    ->columns(2)
                    ->searchable()
                    ->options(function () {
                        // Si l'utilisateur connecté est Super Admin, afficher toutes les permissions
                        if (auth()->user()->hasRole('Super Admin')) {
                            return \Spatie\Permission\Models\Permission::all()
                                ->pluck('name', 'id')
                                ->sort()
                                ->toArray();
                        }

                        // Récupérer toutes les permissions existantes avec leurs IDs
                        $allPermissions = \Spatie\Permission\Models\Permission::all()->keyBy('name');
                        
                        // Récupérer les permissions de l'utilisateur connecté (via rôles + permissions directes)
                        $userPermissions = collect();
                        
                        // Permissions via les rôles
                        $userPermissions = $userPermissions->merge(
                            auth()->user()->getPermissionsViaRoles()->pluck('name')
                        );
                        
                        // Permissions directes
                        $userPermissions = $userPermissions->merge(
                            auth()->user()->getDirectPermissions()->pluck('name')
                        );
                        
                        // Permissions que l'utilisateur peut gérer
                        $managablePermissions = collect();
                        
                        foreach ($userPermissions->unique() as $userPermission) {
                            // Si c'est une permission wildcard (se termine par *)
                            if (str_ends_with($userPermission, '*')) {
                                // Récupérer le préfixe (ex: "user.*" -> "user.")
                                $prefix = str_replace('*', '', $userPermission);
                                
                                // Ajouter toutes les permissions qui commencent par ce préfixe
                                $matchingPermissions = $allPermissions->filter(function ($permission, $name) use ($prefix) {
                                    return str_starts_with($name, $prefix);
                                });
                                
                                $managablePermissions = $managablePermissions->merge($matchingPermissions);
                            } else {
                                // Permission exacte
                                if ($allPermissions->has($userPermission)) {
                                    $managablePermissions->put($userPermission, $allPermissions->get($userPermission));
                                }
                            }
                        }
                        
                        // Retourner les permissions uniques triées par ordre alphabétique
                        return $managablePermissions->unique()
                            ->sortBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->disabled(function ($record) {
                        // Empêcher la modification des permissions des Super Admin par des non-Super Admin
                        if ($record && $record->hasRole('Super Admin')) {
                            return !auth()->user()->hasRole('Super Admin');
                        }
                        return false;
                    }),
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
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Rôles')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Super Admin' ? 'danger' : 'primary')
                    ->separator(','),
                IconColumn::make('app_authentication_secret')
                    ->label('MFA App')
                    ->boolean()
                    ->getStateUsing(fn ($record) => ! empty($record->app_authentication_secret))
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rôles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('resetMfa')
                    ->label('Réinitialiser MFA')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => ! empty($record->app_authentication_secret) || $record->has_email_authentication)
                    ->action(function ($record) {
                        $record->forceFill([
                            'app_authentication_secret' => null,
                            'app_authentication_recovery_codes' => null,
                            'has_email_authentication' => false,
                        ])->save();
                    }),
                Action::make('generateApiToken')
                    ->label('Générer clé API')
                    ->icon('heroicon-o-key')
                    ->action(function ($record) {
                        app(ApiTokenService::class)->createToken($record, 'managed-from-user-resource', ['*']);
                    }),
                EditAction::make()
                    ->visible(function ($record) {
                        return static::canEdit($record);
                    }),
                DeleteAction::make()
                    ->visible(function ($record) {
                        return static::canDelete($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            // Filtrer les Super Admin si l'utilisateur n'est pas Super Admin
                            $recordsToDelete = $records->filter(function ($record) {
                                if ($record->hasRole('Super Admin')) {
                                    return auth()->user()->hasRole('Super Admin');
                                }
                                return true;
                            });

                            $recordsToDelete->each->delete();
                        }),
                ]),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}