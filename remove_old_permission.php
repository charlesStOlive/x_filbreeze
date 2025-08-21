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
    echo "Permission trouvée: " . $perm->name . PHP_EOL;
    echo "Rôles assignés: " . $perm->roles()->count() . PHP_EOL;

    if ($perm->roles()->count() > 0) {
        echo "Rôles assignés:" . PHP_EOL;
        $perm->roles()->each(function ($role) {
            echo "  - " . $role->name . PHP_EOL;
            $role->revokePermissionTo('old_permission_to_delete');
            echo "    ✅ Permission retirée du rôle " . $role->name . PHP_EOL;
        });
        echo PHP_EOL . "✨ Permission 'old_permission_to_delete' retirée de tous les rôles!" . PHP_EOL;
    } else {
        echo "Aucun rôle assigné." . PHP_EOL;
    }
} else {
    echo "Permission 'old_permission_to_delete' non trouvée." . PHP_EOL;
}
