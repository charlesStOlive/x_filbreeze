<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Créer les permissions
        $permissions = [
            // Users
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // Roles
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',

            // Permissions
            'view_permissions',
            'create_permissions',
            'edit_permissions',
            'delete_permissions',

            // Dashboard
            'view_dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Créer les rôles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Assigner toutes les permissions à l'admin
        $adminRole->givePermissionTo(Permission::all());

        // Assigner quelques permissions de base aux utilisateurs
        $userRole->givePermissionTo([
            'view_dashboard',
        ]);

        // Créer un utilisateur admin par défaut s'il n'existe pas
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );

        $admin->assignRole($adminRole);
    }
}
