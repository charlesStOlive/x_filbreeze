<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement le 2025-08-21 13:00:43
 * 
 * Ce seeder contient toutes les permissions et rôles du système
 * avec leurs associations, mais sans les utilisateurs.
 * 
 * Généré avec: php artisan permissions:generate-seeder
 */
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Désactiver les contraintes de clés étrangères temporairement
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        $this->command->info('🌱 Création des permissions...');
        $this->createPermissions();
        
        $this->command->info('👥 Création des rôles...');
        $this->createRoles();
        
        $this->command->info('🔗 Association des permissions aux rôles...');
        $this->assignPermissionsToRoles();
        
        // Réactiver les contraintes
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('✅ Seeder de permissions terminé !');
    }

    /**
     * Créer toutes les permissions
     */
    private function createPermissions(): void
    {
        // Permissions admin
        Permission::firstOrCreate(['name' => 'admin.*']);

        // Permissions crm
        Permission::firstOrCreate(['name' => 'crm.*']);
        Permission::firstOrCreate(['name' => 'crm.company.*']);
        Permission::firstOrCreate(['name' => 'crm.company.view']);
        Permission::firstOrCreate(['name' => 'crm.company.create']);
        Permission::firstOrCreate(['name' => 'crm.company.edit']);
        Permission::firstOrCreate(['name' => 'crm.company.delete']);
        Permission::firstOrCreate(['name' => 'crm.contact.*']);
        Permission::firstOrCreate(['name' => 'crm.contact.view']);
        Permission::firstOrCreate(['name' => 'crm.contact.create']);
        Permission::firstOrCreate(['name' => 'crm.contact.edit']);
        Permission::firstOrCreate(['name' => 'crm.contact.delete']);
        Permission::firstOrCreate(['name' => 'crm.invoice.*']);
        Permission::firstOrCreate(['name' => 'crm.invoice.view']);
        Permission::firstOrCreate(['name' => 'crm.invoice.create']);
        Permission::firstOrCreate(['name' => 'crm.invoice.edit']);
        Permission::firstOrCreate(['name' => 'crm.invoice.delete']);
        Permission::firstOrCreate(['name' => 'crm.quote.*']);
        Permission::firstOrCreate(['name' => 'crm.quote.view']);
        Permission::firstOrCreate(['name' => 'crm.quote.create']);
        Permission::firstOrCreate(['name' => 'crm.quote.edit']);
        Permission::firstOrCreate(['name' => 'crm.quote.delete']);
        Permission::firstOrCreate(['name' => 'crm.sector.*']);
        Permission::firstOrCreate(['name' => 'crm.sector.view']);
        Permission::firstOrCreate(['name' => 'crm.sector.create']);
        Permission::firstOrCreate(['name' => 'crm.sector.edit']);
        Permission::firstOrCreate(['name' => 'crm.sector.delete']);
        Permission::firstOrCreate(['name' => 'crm.supplierinvoice.*']);
        Permission::firstOrCreate(['name' => 'crm.supplierinvoice.view']);
        Permission::firstOrCreate(['name' => 'crm.supplierinvoice.create']);
        Permission::firstOrCreate(['name' => 'crm.supplierinvoice.edit']);
        Permission::firstOrCreate(['name' => 'crm.supplierinvoice.delete']);
        Permission::firstOrCreate(['name' => 'crm.supplier.*']);
        Permission::firstOrCreate(['name' => 'crm.supplier.view']);
        Permission::firstOrCreate(['name' => 'crm.supplier.create']);
        Permission::firstOrCreate(['name' => 'crm.supplier.edit']);
        Permission::firstOrCreate(['name' => 'crm.supplier.delete']);

        // Permissions datasets
        Permission::firstOrCreate(['name' => 'datasets.*']);
        Permission::firstOrCreate(['name' => 'datasets.product.*']);
        Permission::firstOrCreate(['name' => 'datasets.product.view']);
        Permission::firstOrCreate(['name' => 'datasets.product.create']);
        Permission::firstOrCreate(['name' => 'datasets.product.edit']);
        Permission::firstOrCreate(['name' => 'datasets.product.delete']);

        // Permissions msgraph
        Permission::firstOrCreate(['name' => 'msgraph.*']);
        Permission::firstOrCreate(['name' => 'msgraph.msgdraftuser.*']);
        Permission::firstOrCreate(['name' => 'msgraph.msgdraftuser.view']);
        Permission::firstOrCreate(['name' => 'msgraph.msgdraftuser.create']);
        Permission::firstOrCreate(['name' => 'msgraph.msgdraftuser.edit']);
        Permission::firstOrCreate(['name' => 'msgraph.msgdraftuser.delete']);
        Permission::firstOrCreate(['name' => 'msgraph.msginuser.*']);
        Permission::firstOrCreate(['name' => 'msgraph.msginuser.view']);
        Permission::firstOrCreate(['name' => 'msgraph.msginuser.create']);
        Permission::firstOrCreate(['name' => 'msgraph.msginuser.edit']);
        Permission::firstOrCreate(['name' => 'msgraph.msginuser.delete']);

        // Permissions permission
        Permission::firstOrCreate(['name' => 'permission.*']);
        Permission::firstOrCreate(['name' => 'permission.view']);
        Permission::firstOrCreate(['name' => 'permission.create']);
        Permission::firstOrCreate(['name' => 'permission.edit']);
        Permission::firstOrCreate(['name' => 'permission.delete']);

        // Permissions permissions
        Permission::firstOrCreate(['name' => 'permissions.*']);

        // Permissions role
        Permission::firstOrCreate(['name' => 'role.*']);
        Permission::firstOrCreate(['name' => 'role.view']);
        Permission::firstOrCreate(['name' => 'role.create']);
        Permission::firstOrCreate(['name' => 'role.edit']);
        Permission::firstOrCreate(['name' => 'role.delete']);

        // Permissions roles
        Permission::firstOrCreate(['name' => 'roles.*']);

        // Permissions s_special_permission
        Permission::firstOrCreate(['name' => 's_special_permission']);

        // Permissions s_system_config
        Permission::firstOrCreate(['name' => 's_system_config']);

        // Permissions user
        Permission::firstOrCreate(['name' => 'user.*']);
        Permission::firstOrCreate(['name' => 'user.view']);
        Permission::firstOrCreate(['name' => 'user.create']);
        Permission::firstOrCreate(['name' => 'user.edit']);
        Permission::firstOrCreate(['name' => 'user.delete']);

        // Permissions users
        Permission::firstOrCreate(['name' => 'users.*']);
    }

    /**
     * Créer tous les rôles
     */
    private function createRoles(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'user']);
        Role::firstOrCreate(['name' => 'crm_manager']);
        Role::firstOrCreate(['name' => 'product_manager']);

    }

    /**
     * Associer les permissions aux rôles
     */
    private function assignPermissionsToRoles(): void
    {
        // Permissions pour le rôle 'admin'
        $roleadmin = Role::where('name', 'admin')->first();
        $roleadmin->givePermissionTo([
            'admin.*', 'users.*', 'roles.*',
            'permissions.*', 'permission.*', 'permission.view',
            'permission.create', 'permission.edit', 'permission.delete',
            'role.*', 'role.view', 'role.create',
            'role.edit', 'role.delete', 'user.*',
            'user.view', 'user.create', 'user.edit',
            'user.delete', 's_special_permission', 's_system_config',
            'crm.*', 'crm.company.*', 'crm.company.view',
            'crm.company.create', 'crm.company.edit', 'crm.company.delete',
            'crm.contact.*', 'crm.contact.view', 'crm.contact.create',
            'crm.contact.edit', 'crm.contact.delete', 'crm.invoice.*',
            'crm.invoice.view', 'crm.invoice.create', 'crm.invoice.edit',
            'crm.invoice.delete', 'crm.quote.*', 'crm.quote.view',
            'crm.quote.create', 'crm.quote.edit', 'crm.quote.delete',
            'crm.sector.*', 'crm.sector.view', 'crm.sector.create',
            'crm.sector.edit', 'crm.sector.delete', 'crm.supplierinvoice.*',
            'crm.supplierinvoice.view', 'crm.supplierinvoice.create', 'crm.supplierinvoice.edit',
            'crm.supplierinvoice.delete', 'crm.supplier.*', 'crm.supplier.view',
            'crm.supplier.create', 'crm.supplier.edit', 'crm.supplier.delete',
            'datasets.*', 'datasets.product.*', 'datasets.product.view',
            'datasets.product.create', 'datasets.product.edit', 'datasets.product.delete',
            'msgraph.*', 'msgraph.msgdraftuser.*', 'msgraph.msgdraftuser.view',
            'msgraph.msgdraftuser.create', 'msgraph.msgdraftuser.edit', 'msgraph.msgdraftuser.delete',
            'msgraph.msginuser.*', 'msgraph.msginuser.view', 'msgraph.msginuser.create',
            'msgraph.msginuser.edit', 'msgraph.msginuser.delete'
        ]);
    }
}