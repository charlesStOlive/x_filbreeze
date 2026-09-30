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

        // Rôles et permissions (noms de filament-permission-manager, créés par permissions:sync).
        $this->call(RolePermissionSeeder::class);

        // Garder les autres seeders
        $this->call(SectorSeeder::class);
        $this->call(SupplierSeeder::class);
    }
}
