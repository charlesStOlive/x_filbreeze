<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: __DIR__)
    ->withRouting(
        web: __DIR__ . '/routes/web.php',
        api: __DIR__ . '/routes/api.php',
        commands: __DIR__ . '/routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Méthode directe avec DB
$result = \DB::table('role_has_permissions')
    ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
    ->where('permissions.name', 'old_permission_to_delete')
    ->get();

echo "Résultats DB pour old_permission_to_delete:" . PHP_EOL;
foreach ($result as $row) {
    echo "Role ID: " . $row->role_id . ", Permission ID: " . $row->permission_id . PHP_EOL;
}

// Supprimer directement via DB
$permission = Permission::where('name', 'old_permission_to_delete')->first();
if ($permission) {
    $deleted = \DB::table('role_has_permissions')
        ->where('permission_id', $permission->id)
        ->delete();

    echo "Supprimé " . $deleted . " relation(s) role_has_permissions." . PHP_EOL;

    // Vérifier après suppression
    $countAfter = $permission->roles()->count();
    echo "Rôles assignés après suppression: " . $countAfter . PHP_EOL;
}
