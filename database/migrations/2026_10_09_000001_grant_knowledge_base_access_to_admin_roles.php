<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Le centre d'aide (/aide) est pour tous ceux qui entrent dans l'admin : les rôles qui ont `panels.admin.access`
 * reçoivent `panels.knowledge-base.access` (filament-permission-manager, HasPanelPermission).
 */
return new class extends Migration
{
    public function up(): void
    {
        $access = Permission::findOrCreate('panels.knowledge-base.access', 'web');

        Role::query()->get()
            ->filter(fn (Role $role): bool => $role->hasPermissionTo('panels.admin.access') || $role->hasPermissionTo('panels.*'))
            ->each(fn (Role $role) => $role->givePermissionTo($access));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // L'accès donné ne se retire pas : un rôle a pu le recevoir autrement depuis.
    }
};
