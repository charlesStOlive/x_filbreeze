<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;

class GeneratePermissionSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:generate-seeder 
                            {--file=PermissionSeeder : Nom du fichier seeder}
                            {--path=database/seeders : Dossier de destination}
                            {--cluster= : Générer seulement pour un cluster spécifique}
                            {--role= : Générer seulement pour un rôle spécifique}
                            {--permissions-only : Générer seulement les permissions}
                            {--roles-only : Générer seulement les rôles}
                            {--with-fake-users : Inclure la création de faux utilisateurs de test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère un seeder avec toutes les permissions et rôles actuels';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🌱 Génération du seeder de permissions...');

        $fileName = $this->option('file');
        $seedersPath = $this->option('path');
        $filePath = base_path($seedersPath . '/' . $fileName . '.php');

        // Collecter les données selon les filtres
        $permissions = $this->collectPermissions();
        $roles = $this->collectRoles();

        if ($permissions->isEmpty() && $roles->isEmpty()) {
            $this->error('❌ Aucune donnée trouvée avec les filtres spécifiés !');
            return;
        }

        $this->info("📋 Trouvé {$permissions->count()} permission(s) et {$roles->count()} rôle(s)");

        // Demander si l'utilisateur veut créer des faux utilisateurs
        $createFakeUsers = $this->option('with-fake-users') ||
            $this->confirm('Voulez-vous inclure la création de faux utilisateurs de test ?', false);

        // Générer le contenu du seeder
        $seederContent = $this->generateSeederContent($permissions, $roles, $fileName, $createFakeUsers);

        // Créer le dossier s'il n'existe pas
        if (!File::exists(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true);
        }

        // Écrire le fichier
        File::put($filePath, $seederContent);

        $this->info("✅ Seeder généré avec succès : {$filePath}");
        $this->newLine();
        $this->comment('Pour utiliser ce seeder :');
        $this->line("1. Ajouter '{$fileName}::class,' dans DatabaseSeeder.php");
        $this->line("2. Exécuter : php artisan db:seed --class={$fileName}");
        $this->line("3. Ou exécuter : php artisan db:seed");

        if ($this->option('cluster')) {
            $this->newLine();
            $this->comment("🔍 Filtré pour le cluster : " . $this->option('cluster'));
        }
        if ($this->option('role')) {
            $this->newLine();
            $this->comment("👤 Filtré pour le rôle : " . $this->option('role'));
        }
        if ($createFakeUsers) {
            $this->newLine();
            $this->comment("👥 Faux utilisateurs inclus dans le seeder");
        }
    }

    private function collectPermissions()
    {
        $query = Permission::query();

        if ($this->option('roles-only')) {
            return collect();
        }

        if ($cluster = $this->option('cluster')) {
            $query->where('name', 'like', $cluster . '.%')
                ->orWhere('name', $cluster . '.*');
        }

        return $query->get();
    }

    private function collectRoles()
    {
        $query = Role::with('permissions');

        if ($this->option('permissions-only')) {
            return collect();
        }

        if ($role = $this->option('role')) {
            $query->where('name', $role);
        }

        $roles = $query->get();

        // Si on filtre par cluster, filtrer aussi les permissions des rôles
        if ($cluster = $this->option('cluster')) {
            foreach ($roles as $role) {
                $filteredPermissions = $role->permissions->filter(function ($permission) use ($cluster) {
                    return str_starts_with($permission->name, $cluster . '.') ||
                        $permission->name === $cluster . '.*';
                });
                $role->setRelation('permissions', $filteredPermissions);
            }
        }

        return $roles;
    }

    private function generateSeederContent($permissions, $roles, $className, $createFakeUsers = false): string
    {
        $timestamp = now()->format('Y-m-d H:i:s');

        // Charger le stub depuis le dossier spécifique à la commande
        $stubPath = app_path('Console/Commands/stubs/Permissions/permission_seeder.stub');
        if (!File::exists($stubPath)) {
            throw new \Exception("Fichier stub introuvable : {$stubPath}");
        }

        $stub = File::get($stubPath);

        // Préparer les données pour le remplacement
        $commandOptions = $this->buildCommandOptionsString();

        // Grouper les permissions par préfixe pour une meilleure organisation
        $groupedPermissions = $this->groupPermissionsByPrefix($permissions);

        // Générer les données des permissions
        $permissionsData = $this->generatePermissionsData($groupedPermissions);

        // Générer les données des rôles
        $rolesData = $this->generateRolesData($roles);

        // Générer les associations rôles-permissions
        $rolePermissionsData = $this->generateRolePermissionsData($roles);

        // Gérer les utilisateurs factices
        $usersData = $createFakeUsers ? $this->generateFakeUsersData($roles) : '';

        // Replacements dans le stub
        $replacements = [
            '{{timestamp}}' => $timestamp,
            '{{class_name}}' => $className,
            '{{permissions_count}}' => $permissions->count(),
            '{{roles_count}}' => $roles->count(),
            '{{command_options}}' => $commandOptions,
            '{{permissions_data}}' => $permissionsData,
            '{{roles_data}}' => $rolesData,
            '{{role_permissions_data}}' => $rolePermissionsData,
            '{{use_users}}' => $createFakeUsers ? "\nuse App\Models\User;\nuse Illuminate\Support\Facades\Hash;" : '',
            '{{users_comment}}' => $createFakeUsers ? ', ainsi que des utilisateurs de test' : '',
            '{{users_stats}}' => $createFakeUsers ? "\n * - Utilisateurs de test inclus" : '',
            '{{create_users_call}}' => $createFakeUsers ? "\n        \$this->command->info('👤 Création des utilisateurs de test...');\n        \$this->createFakeUsers();\n        " : '',
            '{{create_users_method}}' => $createFakeUsers ? $usersData : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    private function buildCommandOptionsString(): string
    {
        $options = [];

        if ($this->option('cluster')) {
            $options[] = "--cluster=" . $this->option('cluster');
        }
        if ($this->option('role')) {
            $options[] = "--role=" . $this->option('role');
        }
        if ($this->option('permissions-only')) {
            $options[] = "--permissions-only";
        }
        if ($this->option('roles-only')) {
            $options[] = "--roles-only";
        }
        if ($this->option('with-fake-users')) {
            $options[] = "--with-fake-users";
        }

        return $options ? ' ' . implode(' ', $options) : '';
    }

    private function groupPermissionsByPrefix($permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $prefix = $parts[0];

            if (!isset($grouped[$prefix])) {
                $grouped[$prefix] = [];
            }

            $grouped[$prefix][] = $permission;
        }

        // Trier les groupes
        ksort($grouped);

        return $grouped;
    }

    private function generatePermissionsData($groupedPermissions): string
    {
        $content = '';

        foreach ($groupedPermissions as $prefix => $permissions) {
            $content .= "        // Permissions {$prefix}\n";

            foreach ($permissions as $permission) {
                $content .= "        Permission::firstOrCreate(['name' => '{$permission->name}']);\n";
            }

            $content .= "\n";
        }

        return rtrim($content);
    }

    private function generateRolesData($roles): string
    {
        $content = '';

        foreach ($roles as $role) {
            $content .= "        Role::firstOrCreate(['name' => '{$role->name}']);\n";
        }

        return $content;
    }

    private function generateRolePermissionsData($roles): string
    {
        $content = '';

        foreach ($roles as $role) {
            if ($role->permissions->count() > 0) {
                $content .= "        // Permissions pour le rôle '{$role->name}'\n";
                $content .= "        \$role{$role->name} = Role::where('name', '{$role->name}')->first();\n";

                $permissionNames = $role->permissions->pluck('name')->map(function ($name) {
                    return "'{$name}'";
                })->toArray();

                // Grouper les permissions par lignes pour la lisibilité
                $chunks = array_chunk($permissionNames, 3);
                $content .= "        \$role{$role->name}->givePermissionTo([\n";

                foreach ($chunks as $chunk) {
                    $content .= "            " . implode(', ', $chunk) . ",\n";
                }

                $content = rtrim($content, ",\n") . "\n";
                $content .= "        ]);\n\n";
            }
        }

        return rtrim($content);
    }

    private function generateFakeUsersData($roles): string
    {
        $content = "\n\n    /**\n     * Créer des utilisateurs de test\n     */\n    private function createFakeUsers(): void\n    {\n";

        // Créer un utilisateur admin
        $content .= "        // Créer un utilisateur admin de test\n";
        $content .= "        \$adminUser = User::firstOrCreate(\n";
        $content .= "            ['email' => 'admin@test.com'],\n";
        $content .= "            [\n";
        $content .= "                'name' => 'Admin Test',\n";
        $content .= "                'password' => Hash::make('password'),\n";
        $content .= "                'email_verified_at' => now(),\n";
        $content .= "            ]\n";
        $content .= "        );\n\n";

        // Assigner le rôle admin
        $adminRole = $roles->where('name', 'admin')->first();
        if ($adminRole) {
            $content .= "        \$adminUser->assignRole('admin');\n\n";
        }

        // Créer d'autres utilisateurs de test pour chaque rôle
        foreach ($roles as $role) {
            if ($role->name !== 'admin') {
                $roleName = ucfirst($role->name);
                $email = strtolower($role->name) . '@test.com';

                $content .= "        // Créer un utilisateur {$roleName} de test\n";
                $content .= "        \${$role->name}User = User::firstOrCreate(\n";
                $content .= "            ['email' => '{$email}'],\n";
                $content .= "            [\n";
                $content .= "                'name' => '{$roleName} Test',\n";
                $content .= "                'password' => Hash::make('password'),\n";
                $content .= "                'email_verified_at' => now(),\n";
                $content .= "            ]\n";
                $content .= "        );\n";
                $content .= "        \${$role->name}User->assignRole('{$role->name}');\n\n";
            }
        }

        $content .= "        \$this->command->info('👤 Utilisateurs de test créés avec le mot de passe: password');\n";
        $content .= "    }";

        return $content;
    }
}
