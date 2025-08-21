<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ListPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:list {--role= : Filtrer par rôle} {--search= : Rechercher dans les permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liste toutes les permissions et rôles du système';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 Système de Permissions');
        $this->info('========================');

        // Lister les rôles
        $this->listRoles();

        $this->newLine();

        // Lister les permissions
        $this->listPermissions();
    }

    private function listRoles(): void
    {
        $this->info('👥 RÔLES:');

        $roles = Role::withCount('permissions')->get();

        if ($roles->isEmpty()) {
            $this->warn('   Aucun rôle trouvé');
            return;
        }

        $roleData = [];
        foreach ($roles as $role) {
            $roleData[] = [
                $role->name,
                $role->permissions_count,
                $role->users()->count()
            ];
        }

        $this->table(['Nom', 'Permissions', 'Utilisateurs'], $roleData);
    }

    private function listPermissions(): void
    {
        $this->info('🔑 PERMISSIONS:');

        $query = Permission::query();

        // Filtrage par rôle
        if ($role = $this->option('role')) {
            $roleModel = Role::where('name', $role)->first();
            if (!$roleModel) {
                $this->error("Rôle '{$role}' non trouvé");
                return;
            }
            $query->whereHas('roles', function ($q) use ($roleModel) {
                $q->where('id', $roleModel->id);
            });
        }

        // Recherche
        if ($search = $this->option('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $permissions = $query->with('roles')->get();

        if ($permissions->isEmpty()) {
            $this->warn('   Aucune permission trouvée');
            return;
        }

        // Grouper par préfixe
        $grouped = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            return $parts[0];
        });

        foreach ($grouped as $prefix => $perms) {
            $this->info("\n📂 {$prefix}:");

            $permData = [];
            foreach ($perms as $permission) {
                $permData[] = [
                    $permission->name,
                    $permission->roles->pluck('name')->join(', ') ?: 'Aucun'
                ];
            }

            $this->table(['Permission', 'Rôles'], $permData);
        }

        $this->info("\n📊 Total: " . $permissions->count() . " permission(s)");
    }
}
