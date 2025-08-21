<?php

require_once 'vendor/autoload.php';

use App\Services\PermissionService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "=== Test du système de permissions wildcard ===\n\n";

    // Vérifier les permissions existantes
    echo "Permissions wildcard disponibles:\n";
    $permissions = Permission::where('name', 'like', '%.*')->get();
    foreach ($permissions as $permission) {
        echo "- {$permission->name}\n";
    }

    echo "\nRôles disponibles:\n";
    $roles = Role::all();
    foreach ($roles as $role) {
        echo "- {$role->name}\n";
    }

    // Créer un utilisateur de test avec le rôle admin
    $adminUser = User::first();
    if ($adminUser && !$adminUser->hasRole('admin')) {
        $adminUser->assignRole('admin');
        echo "\nRôle admin assigné au premier utilisateur.\n";
    }

    if ($adminUser) {
        echo "\nTest des permissions pour l'utilisateur admin:\n";
        auth()->login($adminUser);

        // Test des permissions avec PermissionService
        $testPermissions = [
            'users.view',
            'users.create',
            'roles.edit',
            'permissions.delete',
            'crm.companies.view',
            'msgraph.drafts.create',
            'unknown.permission'
        ];

        foreach ($testPermissions as $permission) {
            $hasPermission = PermissionService::can($permission);
            $status = $hasPermission ? '✓' : '✗';
            echo "  {$status} {$permission}\n";
        }
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
