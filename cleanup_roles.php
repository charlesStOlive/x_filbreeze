<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Supprimer les anciens rôles
    $oldRoles = Role::whereIn('name', ['Basic User', 'Users'])->get();

    foreach ($oldRoles as $role) {
        echo "Suppression du rôle: {$role->name}\n";
        $role->delete();
    }

    if ($oldRoles->count() > 0) {
        echo "Rôles supprimés avec succès.\n";
    } else {
        echo "Aucun ancien rôle à supprimer.\n";
    }

    // Afficher les rôles restants
    echo "\nRôles actuels:\n";
    $roles = Role::all();
    foreach ($roles as $role) {
        echo "- {$role->name}\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
