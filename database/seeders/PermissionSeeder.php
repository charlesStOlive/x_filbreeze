<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;


/**
 * Seeder généré automatiquement le 2025-08-21 13:54:21
 * 
 * Ce seeder contient toutes les permissions et rôles du système
 * avec leurs associations.
 * 
 * Généré avec: php artisan permissions:generate-seeder --cluster=CRM
 * 
 * Statistiques:
 * - 36 permission(s)
 * - 4 rôle(s)
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

    }

}
