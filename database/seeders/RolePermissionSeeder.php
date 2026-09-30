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

        // Définir les permissions avec système de wildcards
        $permissions = [
            // Permissions globales d'administration
            'admin.*',                    // Accès total admin
            'dashboard.view',            // Voir le dashboard

            // Gestion des utilisateurs avec wildcards
            'users.*',                   // Toutes les actions sur les utilisateurs
            'users.view',               // Voir les utilisateurs
            'users.create',             // Créer des utilisateurs
            'users.edit',               // Modifier des utilisateurs
            'users.delete',             // Supprimer des utilisateurs

            // Gestion des rôles avec wildcards
            'roles.*',                  // Toutes les actions sur les rôles
            'roles.view',              // Voir les rôles
            'roles.create',            // Créer des rôles
            'roles.edit',              // Modifier des rôles
            'roles.delete',            // Supprimer des rôles

            // Gestion des permissions avec wildcards
            'permissions.*',           // Toutes les actions sur les permissions
            'permissions.view',        // Voir les permissions
            'permissions.create',      // Créer des permissions
            'permissions.edit',        // Modifier des permissions
            'permissions.delete',      // Supprimer des permissions

            // CRM avec wildcards
            'crm.*',                   // Accès total CRM
            'crm.companies.*',         // Toutes les actions sur les entreprises
            'crm.companies.view',      // Voir les entreprises
            'crm.companies.create',    // Créer des entreprises
            'crm.companies.edit',      // Modifier des entreprises
            'crm.companies.delete',    // Supprimer des entreprises

            'crm.contacts.*',          // Toutes les actions sur les contacts
            'crm.contacts.view',       // Voir les contacts
            'crm.contacts.create',     // Créer des contacts
            'crm.contacts.edit',       // Modifier des contacts
            'crm.contacts.delete',     // Supprimer des contacts

            'crm.suppliers.*',         // Toutes les actions sur les fournisseurs
            'crm.suppliers.view',      // Voir les fournisseurs
            'crm.suppliers.create',    // Créer des fournisseurs
            'crm.suppliers.edit',      // Modifier des fournisseurs
            'crm.suppliers.delete',    // Supprimer des fournisseurs

            'crm.invoices.*',          // Toutes les actions sur les factures
            'crm.invoices.view',       // Voir les factures
            'crm.invoices.create',     // Créer des factures
            'crm.invoices.edit',       // Modifier des factures
            'crm.invoices.delete',     // Supprimer des factures

            'crm.quotes.*',            // Toutes les actions sur les devis
            'crm.quotes.view',         // Voir les devis
            'crm.quotes.create',       // Créer des devis
            'crm.quotes.edit',         // Modifier des devis
            'crm.quotes.delete',       // Supprimer des devis

            // Produits et catalogues
            'products.*',              // Toutes les actions sur les produits
            'products.view',           // Voir les produits
            'products.create',         // Créer des produits
            'products.edit',           // Modifier des produits
            'products.delete',         // Supprimer des produits

            // MS Graph / Email
            'msgraph.*',               // Accès total MS Graph
            'msgraph.emails.view',     // Voir les emails
            'msgraph.emails.send',     // Envoyer des emails
            'msgraph.drafts.*',        // Gestion des brouillons
        ];

        // Créer les permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // Créer les rôles
        $adminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);
        $crmManagerRole = Role::firstOrCreate(['name' => 'crm_manager']);
        $productManagerRole = Role::firstOrCreate(['name' => 'product_manager']);

        // Assigner les permissions aux rôles

        // Admin : Accès total
        $adminRole->syncPermissions(['admin.*']);

        // Utilisateur de base : Dashboard seulement
        $userRole->syncPermissions(['dashboard.view']);

        // CRM Manager : Gestion CRM + vue utilisateurs
        $crmManagerRole->syncPermissions([
            'dashboard.view',
            'crm.*',
            'users.view',
        ]);

        // Product Manager : Gestion produits + vue CRM limitée
        $productManagerRole->syncPermissions([
            'dashboard.view',
            'products.*',
            'crm.companies.view',
            'crm.contacts.view',
        ]);

        // Créer ou mettre à jour les utilisateurs par défaut
        $admin = \App\Models\User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );
        $admin->syncRoles(['Super Admin']);

        // Créer un utilisateur CRM Manager
        $crmManager = \App\Models\User::updateOrCreate(
            ['email' => 'crm@example.com'],
            [
                'name' => 'CRM Manager',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );
        $crmManager->syncRoles(['crm_manager']);

        // Créer un utilisateur Product Manager
        $productManager = \App\Models\User::updateOrCreate(
            ['email' => 'product@example.com'],
            [
                'name' => 'Product Manager',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );
        $productManager->syncRoles(['product_manager']);
    }
}
