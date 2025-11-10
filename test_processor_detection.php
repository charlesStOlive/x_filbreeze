<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "=== Test de détection des processeurs ===\n";

    // Test du service principal
    $service = app('App\Services\EmailsProcessorRegisterServices');
    echo "✓ Service EmailsProcessorRegisterServices chargé\n";

    // Test de la détection des processeurs draft
    $draftProcessors = $service->getEmailDraftProcessors();
    echo "Processeurs email-draft détectés:\n";
    foreach ($draftProcessors as $key => $processor) {
        echo "  - $key: " . $processor . "\n";
    }

    // Test de la détection des processeurs in
    $inProcessors = $service->getEmailInProcessors();
    echo "Processeurs email-in détectés:\n";
    foreach ($inProcessors as $key => $processor) {
        echo "  - $key: " . $processor . "\n";
    }

    // Test de création d'une instance
    if (!empty($draftProcessors)) {
        $firstKey = array_key_first($draftProcessors);
        $firstClass = $draftProcessors[$firstKey];

        if (class_exists($firstClass)) {
            echo "✓ Classe '$firstClass' existe\n";

            // Vérifier les méthodes statiques
            $reflection = new ReflectionClass($firstClass);
            $methods = ['getKey', 'getLabel', 'getIcon', 'getDescription'];
            foreach ($methods as $method) {
                if ($reflection->hasMethod($method) && $reflection->getMethod($method)->isStatic()) {
                    echo "  - Méthode $method() disponible\n";
                } else {
                    echo "  - MANQUE: Méthode $method() statique\n";
                }
            }
        } else {
            echo "✗ Classe '$firstClass' n'existe pas\n";
        }
    }

    echo "\n=== Test terminé avec succès ===\n";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
