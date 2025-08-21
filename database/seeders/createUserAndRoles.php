<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash; // Importer la classe Hash

class createUserAndRoles extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer le rôle superadmin s'il n'existe pas
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        // Créer un utilisateur
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'charles@notilac.fr',
            'password' => Hash::make('1234'), // Utiliser Hash::make() pour hasher le mot de passe
        ]);
        // Assigner le rôle superadmin à cet utilisateur
        $user->assignRole($superAdminRole);

        // Créer le rôle superadmin s'il n'existe pas
        $userRole = Role::firstOrCreate(['name' => 'Basic User']);
        // Créer un utilisateur
        $userTest = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => Hash::make('1234'), // Utiliser Hash::make() pour hasher le mot de passe
        ]);
        // Assigner le rôle superadmin à cet utilisateur
        $userTest->assignRole($userRole);

        // Créer le rôle "Users"
        $userRole = Role::firstOrCreate(['name' => 'Users']);

        // Avec le système wildcard, on donne des permissions spécifiques
        $userPermissions = [
            'users.view',
            'users.create',
            'users.edit',
        ];

        foreach ($userPermissions as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName]);
            $userRole->givePermissionTo($permission);
        }

        $userAdmin = User::create([
            'name' => 'Admin User',
            'email' => 'adminuser@test.com',
            'password' => Hash::make('1234'), // Utiliser Hash::make() pour hasher le mot de passe
        ]);
        // Assigner le rôle superadmin à cet utilisateur
        $userAdmin->assignRole($userRole);
    }
}
