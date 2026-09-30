<?php

namespace App\Filament\Clusters\Administration\UserResource\Schemas;

use CharlesStOlive\FilamentPermissionManager\Support\SuperAdmin;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
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

                TextEntry::make('super_admin_warning')
                    ->label('')
                    ->state('⚠️ Cet utilisateur est Super Admin : son rôle reçoit toutes les permissions à chaque synchronisation.')
                    ->visible(fn($record) => $record && SuperAdmin::is($record))
                    ->columnSpanFull(),

                Select::make('roles')
                    ->label('Rôles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable()
                    ->options(function () {
                        if (SuperAdmin::is(auth()->user())) {
                            return \Spatie\Permission\Models\Role::all()->pluck('name', 'id');
                        }

                        return \Spatie\Permission\Models\Role::where('name', '!=', SuperAdmin::role())
                            ->pluck('name', 'id');
                    })
                    ->disabled(function ($record) {
                        if ($record && SuperAdmin::is($record)) {
                            return ! SuperAdmin::is(auth()->user());
                        }

                        return false;
                    }),

                CheckboxList::make('permissions')
                    ->label('Permissions directes')
                    ->relationship('permissions', 'name')
                    ->columns(2)
                    ->searchable()
                    ->options(function () {
                        if (SuperAdmin::is(auth()->user())) {
                            return \Spatie\Permission\Models\Permission::all()
                                ->pluck('name', 'id')
                                ->sort()
                                ->toArray();
                        }

                        $allPermissions = \Spatie\Permission\Models\Permission::all()->keyBy('name');

                        $userPermissions = collect()
                            ->merge(auth()->user()->getPermissionsViaRoles()->pluck('name'))
                            ->merge(auth()->user()->getDirectPermissions()->pluck('name'));

                        $managablePermissions = collect();

                        foreach ($userPermissions->unique() as $userPermission) {
                            if (str_ends_with($userPermission, '*')) {
                                $prefix = str_replace('*', '', $userPermission);
                                $matchingPermissions = $allPermissions->filter(
                                    fn($permission, $name) => str_starts_with($name, $prefix)
                                );
                                $managablePermissions = $managablePermissions->merge($matchingPermissions);
                            } elseif ($allPermissions->has($userPermission)) {
                                $managablePermissions->put($userPermission, $allPermissions->get($userPermission));
                            }
                        }

                        return $managablePermissions->unique()
                            ->sortBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->disabled(function ($record) {
                        if ($record && SuperAdmin::is($record)) {
                            return ! SuperAdmin::is(auth()->user());
                        }

                        return false;
                    }),
            ]);
    }
}
