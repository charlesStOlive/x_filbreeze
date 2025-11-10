<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "=== Test du service dans le plugin ===\n";

    // Test du service du plugin
    $pluginService = new \CharlesStOlive\MsGraphFilament\Services\EmailsProcessorRegisterServices();
    echo "✓ Service du plugin EmailsProcessorRegisterServices chargé\n";

    // Test de la détection des processeurs draft
    $draftProcessors = $pluginService->getEmailDraftProcessors();
    echo "Processeurs email-draft détectés (plugin):\n";
    foreach ($draftProcessors as $key => $processor) {
        echo "  - $key: " . $processor . "\n";
    }

    // Test de la détection des processeurs in
    $inProcessors = $pluginService->getEmailInProcessors();
    echo "Processeurs email-in détectés (plugin):\n";
    foreach ($inProcessors as $key => $processor) {
        echo "  - $key: " . $processor . "\n";
    }

    // Test de getAll() pour email-draft
    $allDraftServices = $pluginService->getAll('email-draft');
    echo "\nServices getAll('email-draft'):\n";
    foreach ($allDraftServices as $key => $service) {
        echo "  - $key: {$service['label']} ({$service['class']})\n";
    }

    echo "\n=== Test terminé avec succès ===\n";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
