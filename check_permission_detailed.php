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

$perm = Permission::where('name', 'old_permission_to_delete')->first();

if ($perm) {
    echo "Permission: " . $perm->name . PHP_EOL;
    echo "Rôles assignés (count): " . $perm->roles()->count() . PHP_EOL;
    echo "Utilisateurs assignés (count): " . $perm->users()->count() . PHP_EOL;

    $roles = $perm->roles()->get();
    if ($roles->count() > 0) {
        echo "Rôles encore assignés:" . PHP_EOL;
        foreach ($roles as $role) {
            echo "  - " . $role->name . " (ID: " . $role->id . ")" . PHP_EOL;
        }
    } else {
        echo "✅ Aucun rôle assigné" . PHP_EOL;
    }

    $users = $perm->users()->get();
    if ($users->count() > 0) {
        echo "Utilisateurs encore assignés:" . PHP_EOL;
        foreach ($users as $user) {
            echo "  - " . $user->name . " (ID: " . $user->id . ")" . PHP_EOL;
        }
    } else {
        echo "✅ Aucun utilisateur assigné" . PHP_EOL;
    }
} else {
    echo "Permission 'old_permission_to_delete' non trouvée." . PHP_EOL;
}
