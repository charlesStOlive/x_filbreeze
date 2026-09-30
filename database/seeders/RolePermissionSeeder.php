<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Les rôles de filbreeze et leurs droits, avec les noms de filament-permission-manager (les mêmes que dans les autres
 * applications) : `{rubrique}.{liste}.{action}`, `panels.admin.access` pour entrer. Les permissions elles-mêmes sont
 * créées par `permissions:sync`, qui lit ce que les écrans déclarent et donne tout au Super Admin : on n'écrit ici que
 * les droits des autres rôles.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $userRole = Role::findOrCreate('user', 'web');
        $crmManagerRole = Role::findOrCreate('crm_manager', 'web');
        $productManagerRole = Role::findOrCreate('product_manager', 'web');

        // Toutes les permissions déclarées, et le rôle Super Admin qui les reçoit toutes.
        Artisan::call('permissions:sync', ['--force' => true]);

        // Utilisateur de base : entrer dans le panel (le tableau de bord est ouvert à tous).
        $userRole->syncPermissions(['panels.admin.access']);

        // CRM Manager : tout le CRM, et voir les utilisateurs.
        $crmManagerRole->syncPermissions([
            'panels.admin.access',
            'crm.*',
            'administration.user.viewany',
            'administration.user.view',
        ]);

        // Product Manager : les produits, et voir les sociétés et les contacts.
        $productManagerRole->syncPermissions([
            'panels.admin.access',
            'datasets.product.*',
            'crm.company.viewany',
            'crm.company.view',
            'crm.contact.viewany',
            'crm.contact.view',
        ]);

        $users = [
            ['admin@example.com', 'Administrator', \CharlesStOlive\FilamentPermissionManager\Support\SuperAdmin::role()],
            ['crm@example.com', 'CRM Manager', 'crm_manager'],
            ['product@example.com', 'Product Manager', 'product_manager'],
        ];

        foreach ($users as [$email, $name, $role]) {
            \App\Models\User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('password')])
                ->syncRoles([$role]);
        }
    }
}
