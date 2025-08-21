<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync {--force : Force sync without confirmation} {--cleanup : Detect and propose to remove obsolete permissions} {--dry-run : Show what would be cleaned without doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise les permissions en scannant toutes les Resources Filament. Peut aussi détecter et supprimer les permissions obsolètes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Scan des Resources Filament...');

        // Scanner les resources
        $resources = $this->scanFilamentResources();

        $this->info("📋 Trouvé " . count($resources) . " resource(s)");

        // Générer les permissions
        $permissions = $this->generatePermissions($resources);

        $this->info("🔐 " . count($permissions) . " permission(s) à synchroniser");

        // Afficher les permissions qui seront créées
        $this->table(['Permission', 'Type'], array_map(function ($perm) {
            return [$perm['name'], $perm['type']];
        }, $permissions));

        if (!$this->option('force') && !$this->confirm('Continuer la synchronisation ?')) {
            $this->info('❌ Synchronisation annulée');
            return;
        }

        // Créer/Mettre à jour les permissions
        $created = 0;
        $existing = 0;

        foreach ($permissions as $permData) {
            $permission = Permission::firstOrCreate([
                'name' => $permData['name']
            ]);

            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("✅ Créé: {$permData['name']}");
            } else {
                $existing++;
                $this->line("ℹ️  Existe: {$permData['name']}");
            }
        }

        // Assigner toutes les permissions au rôle admin
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        $this->info("🎉 Synchronisation terminée !");
        $this->info("   - {$created} permission(s) créée(s)");
        $this->info("   - {$existing} permission(s) existante(s)");
        $this->info("   - Rôle 'admin' mis à jour avec toutes les permissions");

        // Nettoyer les permissions obsolètes si demandé
        if ($this->option('cleanup') || $this->option('dry-run')) {
            $this->cleanupObsoletePermissions($permissions, $this->option('dry-run'));
        }
    }

    private function cleanupObsoletePermissions(array $expectedPermissions, bool $dryRun = false): void
    {
        $this->newLine();
        $this->info($dryRun ? '👀 Simulation du nettoyage (dry-run)...' : '🧹 Détection des permissions obsolètes...');

        // Récupérer toutes les permissions existantes
        $existingPermissions = Permission::all();

        // Créer un tableau des noms de permissions attendues
        $expectedNames = collect($expectedPermissions)->pluck('name')->toArray();

        // Trouver les permissions obsolètes (exclure celles qui commencent par 's_')
        $obsoletePermissions = $existingPermissions->filter(function ($permission) use ($expectedNames) {
            return !in_array($permission->name, $expectedNames)
                && !str_starts_with($permission->name, 's_');
        });

        if ($obsoletePermissions->isEmpty()) {
            $this->info('✅ Aucune permission obsolète détectée');
            return;
        }

        $this->warn("⚠️  " . $obsoletePermissions->count() . " permission(s) obsolète(s) détectée(s):");

        // Afficher les permissions obsolètes avec compteurs
        $obsoleteData = [];
        $totalRoleAssignments = 0;
        $totalUserAssignments = 0;
        $permissionsWithAssignments = collect();

        foreach ($obsoletePermissions as $permission) {
            $rolesCount = $permission->roles()->count();
            $usersCount = $permission->users()->count();
            $totalRoleAssignments += $rolesCount;
            $totalUserAssignments += $usersCount;

            if ($rolesCount > 0 || $usersCount > 0) {
                $permissionsWithAssignments->push($permission);
            }

            $obsoleteData[] = [
                $permission->name,
                $rolesCount,
                $usersCount,
                $rolesCount > 0 || $usersCount > 0 ? '⚠️' : '✅'
            ];
        }

        $this->table(['Permission', 'Rôles', 'Utilisateurs', 'Sûr'], $obsoleteData);
        $this->info('ℹ️  Les permissions commençant par "s_" sont automatiquement exclues');

        if ($dryRun) {
            $safeToDelete = $obsoletePermissions->filter(function ($permission) {
                return $permission->roles()->count() === 0 && $permission->users()->count() === 0;
            });

            $this->info("📊 Résumé du dry-run:");
            $this->info("   - {$safeToDelete->count()} permission(s) seraient supprimée(s)");
            $this->info("   - " . ($obsoletePermissions->count() - $safeToDelete->count()) . " permission(s) seraient ignorée(s) (encore utilisées)");
            return;
        }

        // Vérifier s'il y a des permissions encore assignées
        if ($permissionsWithAssignments->isNotEmpty()) {
            $this->newLine();
            $this->warn("⚠️  {$permissionsWithAssignments->count()} permission(s) sont encore assignées :");
            $this->warn("   - À {$totalRoleAssignments} rôle(s)");
            $this->warn("   - À {$totalUserAssignments} utilisateur(s) directement");
            $this->newLine();

            if (!$this->option('force')) {
                $removeAssignments = $this->confirm('Voulez-vous retirer ces permissions de tous les rôles et utilisateurs avant suppression ?');

                if ($removeAssignments) {
                    $this->removePermissionsFromRolesAndUsers($permissionsWithAssignments);
                } else {
                    $this->info('❌ Suppression annulée. Les permissions encore assignées seront ignorées.');
                }
            } else {
                $this->info('🔧 Mode force activé : retrait automatique des permissions des rôles et utilisateurs...');
                $this->removePermissionsFromRolesAndUsers($permissionsWithAssignments);
            }
        }

        // Confirmer la suppression
        if (!$this->option('force') && !$this->confirm('Confirmer la suppression des permissions obsolètes ?')) {
            $this->info('❌ Nettoyage annulé');
            return;
        }

        $this->newLine();
        $this->info('🗑️  Suppression des permissions obsolètes...');

        $deleted = 0;
        $skipped = 0;

        foreach ($obsoletePermissions as $permission) {
            // Vérifier à nouveau si la permission est encore utilisée (peut avoir changé)
            $rolesCount = $permission->fresh()->roles()->count();
            $usersCount = $permission->fresh()->users()->count();

            if ($rolesCount > 0 || $usersCount > 0) {
                $this->warn("⚠️  Ignoré: {$permission->name} (encore utilisée après vérification)");
                $skipped++;
                continue;
            }

            try {
                $permission->delete();
                $this->line("✅ Supprimé: {$permission->name}");
                $deleted++;
            } catch (\Exception $e) {
                $this->error("❌ Erreur lors de la suppression de {$permission->name}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("🧹 Nettoyage terminé !");
        $this->info("   - {$deleted} permission(s) supprimée(s)");
        if ($skipped > 0) {
            $this->info("   - {$skipped} permission(s) ignorée(s) (encore utilisées)");
        }

        // Resynchroniser le rôle admin après nettoyage
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(Permission::all());
            $this->info("   - Rôle 'admin' resynchronisé avec les permissions restantes");
        }
    }

    private function removePermissionsFromRolesAndUsers($permissions): void
    {
        $this->newLine();
        $this->info('🔧 Retrait des permissions des rôles et utilisateurs...');

        foreach ($permissions as $permission) {
            $rolesRemoved = 0;
            $usersRemoved = 0;

            // Retirer des rôles
            $roles = $permission->roles()->get();
            foreach ($roles as $role) {
                $role->revokePermissionTo($permission);
                $this->line("   ↳ Retirée du rôle: {$role->name}");
                $rolesRemoved++;
            }

            // Retirer des utilisateurs
            $users = $permission->users()->get();
            foreach ($users as $user) {
                $user->revokePermissionTo($permission);
                $this->line("   ↳ Retirée de l'utilisateur: {$user->name}");
                $usersRemoved++;
            }

            if ($rolesRemoved > 0 || $usersRemoved > 0) {
                $this->info("✅ Permission '{$permission->name}' retirée de {$rolesRemoved} rôle(s) et {$usersRemoved} utilisateur(s)");
            }
        }

        $this->info('🔧 Toutes les permissions ont été retirées des rôles et utilisateurs.');
    }

    private function scanFilamentResources(): array
    {
        $resources = [
            'standalone' => [],
            'clusters' => []
        ];

        // Scanner le dossier Resources principal (standalone)
        $resourcesPath = app_path('Filament/Resources');
        if (File::exists($resourcesPath)) {
            $resources['standalone'] = $this->scanResourceDirectory($resourcesPath);
        }

        // Scanner les Clusters
        $clustersPath = app_path('Filament/Clusters');
        if (File::exists($clustersPath)) {
            foreach (File::directories($clustersPath) as $clusterDir) {
                $clusterName = strtolower(basename($clusterDir));
                $clusterResourcesPath = $clusterDir . '/Resources';

                if (File::exists($clusterResourcesPath)) {
                    $clusterResources = $this->scanResourceDirectory($clusterResourcesPath);
                    if (!empty($clusterResources)) {
                        $resources['clusters'][$clusterName] = $clusterResources;
                    }
                }
            }
        }

        return $resources;
    }

    private function scanResourceDirectory(string $path): array
    {
        $resources = [];
        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $filename = $file->getFilenameWithoutExtension();
                if (str_ends_with($filename, 'Resource')) {
                    $resourceName = str_replace('Resource', '', $filename);
                    $resources[] = strtolower($resourceName);
                }
            }
        }

        return $resources;
    }

    private function generatePermissions(array $resourcesData): array
    {
        $permissions = [];
        $actions = ['view', 'create', 'edit', 'delete'];

        // Permissions globales système
        $globalPermissions = [
            'admin.*' => 'Admin global',
            'users.*' => 'Utilisateurs global',
            'roles.*' => 'Rôles global',
            'permissions.*' => 'Permissions global'
        ];

        foreach ($globalPermissions as $name => $type) {
            $permissions[] = ['name' => $name, 'type' => $type];
        }

        // Permissions pour les Resources standalone
        foreach ($resourcesData['standalone'] as $resource) {
            // Permission globale pour la resource
            $permissions[] = [
                'name' => "{$resource}.*",
                'type' => ucfirst($resource) . ' global'
            ];

            // Permissions spécifiques
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => "{$resource}.{$action}",
                    'type' => ucfirst($resource) . ' ' . $action
                ];
            }
        }

        // Permissions pour les Clusters
        foreach ($resourcesData['clusters'] as $clusterName => $clusterResources) {
            // Permission globale pour tout le cluster
            $permissions[] = [
                'name' => "{$clusterName}.*",
                'type' => ucfirst($clusterName) . ' cluster global'
            ];

            // Permissions pour chaque Resource du cluster
            foreach ($clusterResources as $resource) {
                // Permission globale pour la resource du cluster
                $permissions[] = [
                    'name' => "{$clusterName}.{$resource}.*",
                    'type' => ucfirst($clusterName) . ' ' . ucfirst($resource) . ' global'
                ];

                // Permissions spécifiques pour la resource du cluster
                foreach ($actions as $action) {
                    $permissions[] = [
                        'name' => "{$clusterName}.{$resource}.{$action}",
                        'type' => ucfirst($clusterName) . ' ' . ucfirst($resource) . ' ' . $action
                    ];
                }
            }
        }

        return $permissions;
    }
}
