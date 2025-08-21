<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Utiliser notre nouveau seeder de permissions avec wildcards
        $this->call(RolePermissionSeeder::class);

        // Seeders générés automatiquement disponibles (générer avec: php artisan permissions:generate-seeder)
        // $this->call(PermissionSeeder::class);           // Toutes les permissions et rôles
        // $this->call(CrmPermissionSeeder::class);        // Seulement le cluster CRM
        // $this->call(PermissionsOnlySeeder::class);      // Seulement les permissions

        // Garder les autres seeders
        $this->call(SectorSeeder::class);
        $this->call(SupplierSeeder::class);
    }
}
