<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Supprimer les anciennes permissions auto-générées (par exemple celles avec des noms comme Company.view-any, User.create, etc.)
        $oldPermissionPatterns = [
            'Company.%',
            'Contact.%',
            'Sector.%',
            'Supplier.%',
            'SupplierInvoice.%',
            'User.%',
            'MsgEmailIn.%',
            'MsgToken.%',
            'MsgUser.%',
        ];

        foreach ($oldPermissionPatterns as $pattern) {
            Permission::where('name', 'LIKE', $pattern)->delete();
        }

        // Supprimer la table breezy_sessions si elle existe encore
        if (Schema::hasTable('breezy_sessions')) {
            Schema::dropIfExists('breezy_sessions');
        }

        // Définir les nouvelles permissions avec système de wildcards
        $newPermissions = [
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

        // Créer les nouvelles permissions
        foreach ($newPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);
        }

        // Mettre à jour les rôles existants
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // L'admin a accès à tout
        $adminRole->syncPermissions(['admin.*']);

        // L'utilisateur de base a accès au dashboard uniquement
        $userRole->syncPermissions(['dashboard.view']);

        // Créer des rôles spécialisés
        $crmManagerRole = Role::firstOrCreate(['name' => 'crm_manager']);
        $crmManagerRole->syncPermissions([
            'dashboard.view',
            'crm.*',
            'users.view',
        ]);

        $productManagerRole = Role::firstOrCreate(['name' => 'product_manager']);
        $productManagerRole->syncPermissions([
            'dashboard.view',
            'products.*',
            'crm.companies.view',
            'crm.contacts.view',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En cas de rollback, on peut recréer les permissions basiques
        $basicPermissions = [
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',
            'view_permissions',
            'create_permissions',
            'edit_permissions',
            'delete_permissions',
            'view_dashboard',
        ];

        foreach ($basicPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
};
