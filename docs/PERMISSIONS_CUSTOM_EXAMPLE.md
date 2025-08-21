# 🔧 Exemple : Commande Personnalisée

Voici un exemple de commande personnalisée pour gérer les permissions par module :

## Créer la commande

```bash
php artisan make:command ManageModulePermissions
```

## Code d'exemple

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ManageModulePermissions extends Command
{
    protected $signature = 'permissions:module 
                           {module : Nom du module (ex: crm, inventory)}
                           {--enable : Activer toutes les permissions du module}
                           {--disable : Désactiver toutes les permissions du module}
                           {--role=* : Rôles à affecter}';

    protected $description = 'Gestion des permissions par module';

    public function handle()
    {
        $module = $this->argument('module');
        $roles = $this->option('role');
        
        if ($this->option('enable')) {
            $this->enableModule($module, $roles);
        } elseif ($this->option('disable')) {
            $this->disableModule($module, $roles);
        } else {
            $this->showModuleStatus($module);
        }
    }

    private function enableModule(string $module, array $roles): void
    {
        $permissions = Permission::where('name', 'like', "{$module}.%")
                                ->orWhere('name', "{$module}.*")
                                ->get();

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->givePermissionTo($permissions);
            $this->info("✅ Module {$module} activé pour le rôle {$roleName}");
        }
    }

    private function disableModule(string $module, array $roles): void
    {
        $permissions = Permission::where('name', 'like', "{$module}.%")
                                ->orWhere('name', "{$module}.*")
                                ->get();

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->revokePermissionTo($permissions);
                $this->info("❌ Module {$module} désactivé pour le rôle {$roleName}");
            }
        }
    }

    private function showModuleStatus(string $module): void
    {
        $permissions = Permission::where('name', 'like', "{$module}.%")
                                ->orWhere('name', "{$module}.*")
                                ->with('roles')
                                ->get();

        $this->info("📊 Status du module: {$module}");
        
        $data = [];
        foreach ($permissions as $permission) {
            $data[] = [
                $permission->name,
                $permission->roles->pluck('name')->join(', ') ?: 'Aucun'
            ];
        }

        $this->table(['Permission', 'Rôles'], $data);
    }
}
```

## Utilisation

```bash
# Voir le statut d'un module
php artisan permissions:module crm

# Activer un module pour des rôles
php artisan permissions:module crm --enable --role=manager --role=admin

# Désactiver un module
php artisan permissions:module crm --disable --role=user
```

## Intégration dans votre workflow

Cette commande peut être intégrée dans vos scripts de déploiement pour :

- Activer/désactiver des modules selon l'environnement
- Gérer les permissions en masse
- Automatiser la configuration des rôles

## Autres idées d'extensions

- **permissions:export** : Exporter les permissions en JSON/CSV
- **permissions:import** : Importer des permissions depuis un fichier
- **permissions:audit** : Audit des permissions et détection d'anomalies
- **permissions:backup** : Sauvegarde du système de permissions
