<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddResourcePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:add-resource {resource : Nom de la resource (ex: product, company)} {--actions=view,create,edit,delete : Actions à créer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ajoute les permissions pour une nouvelle resource Filament';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $resourceName = strtolower($this->argument('resource'));
        $actions = explode(',', $this->option('actions'));

        $this->info("📋 Création des permissions pour la resource: {$resourceName}");

        $permissions = [];

        // Permission globale
        $globalPermission = "{$resourceName}.*";
        $permissions[] = $globalPermission;

        // Permissions spécifiques
        foreach ($actions as $action) {
            $action = trim($action);
            $permissions[] = "{$resourceName}.{$action}";
        }

        $this->table(['Permission'], array_map(function ($perm) {
            return [$perm];
        }, $permissions));

        if (!$this->confirm('Créer ces permissions ?')) {
            $this->info('❌ Création annulée');
            return;
        }

        $created = 0;
        $existing = 0;

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName]);

            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("✅ Créé: {$permissionName}");
            } else {
                $existing++;
                $this->line("ℹ️  Existe: {$permissionName}");
            }
        }

        // Assigner au rôle admin automatiquement
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && !$adminRole->hasPermissionTo($permission)) {
                    $adminRole->givePermissionTo($permission);
                }
            }
            $this->info("✅ Permissions assignées au rôle 'admin'");
        }

        $this->info("🎉 Terminé !");
        $this->info("   - {$created} permission(s) créée(s)");
        $this->info("   - {$existing} permission(s) existante(s)");

        // Générer le code pour la Resource
        $this->generateResourceCode($resourceName);
    }

    private function generateResourceCode(string $resourceName): void
    {
        $className = ucfirst($resourceName) . 'Resource';

        $this->info("\n📝 Code à ajouter dans votre {$className}:");

        $code = "
// Ajoutez cet import en haut du fichier
use App\Services\PermissionService;

// Ajoutez ces méthodes dans votre Resource
public static function canViewAny(): bool
{
    return PermissionService::can('{$resourceName}.view');
}

public static function canCreate(): bool
{
    return PermissionService::can('{$resourceName}.create');
}

public static function canEdit(\$record): bool
{
    return PermissionService::can('{$resourceName}.edit');
}

public static function canDelete(\$record): bool
{
    return PermissionService::can('{$resourceName}.delete');
}";

        $this->line($code);
    }
}
