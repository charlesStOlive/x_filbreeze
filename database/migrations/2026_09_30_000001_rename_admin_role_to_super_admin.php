<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Le rôle tout-puissant porte le même nom dans toutes les applications et tous les plugins : « Super Admin »
 * (filament-permission-manager). filbreeze l'appelait `admin`, alors que son code (UserResource, RoleResource) teste déjà
 * « Super Admin » : personne n'y était reconnu. Le rôle est renommé, avec ses utilisateurs et ses permissions.
 *
 * S'il existe déjà un rôle « Super Admin », les utilisateurs et permissions d'`admin` le rejoignent, puis `admin` disparaît.
 */
return new class extends Migration
{
    private const OLD = 'admin';

    private const NEW = 'Super Admin';

    public function up(): void
    {
        $admin = Role::query()->where('name', self::OLD)->where('guard_name', 'web')->first();

        if ($admin === null) {
            return;
        }

        $superAdmin = Role::query()->where('name', self::NEW)->where('guard_name', 'web')->first();

        if ($superAdmin === null) {
            $admin->update(['name' => self::NEW]);
        } else {
            $superAdmin->givePermissionTo($admin->permissions);
            $admin->users->each->assignRole($superAdmin);
            $admin->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', self::NEW)->where('guard_name', 'web')->update(['name' => self::OLD]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
