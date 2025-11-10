<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use CharlesStOlive\MsGraphFilament\Services\EmailsProcessorRegisterServices;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "=== Test de la méthode getAll() utilisée par MailServiceCell ===\n";

    // Test direct de la méthode utilisée dans MailServiceCell
    $services = EmailsProcessorRegisterServices::getAll('email-draft');
    echo "Services retournés par getAll('email-draft'):\n";

    if (empty($services)) {
        echo "❌ AUCUN SERVICE DETECTÉ\n";
    } else {
        echo "✓ " . count($services) . " services détectés:\n";
        foreach ($services as $key => $service) {
            echo "  - Key: {$key}\n";
            echo "    Label: {$service['label']}\n";
            echo "    Class: {$service['class']}\n";
            echo "    Description: {$service['description']}\n";
            if (!empty($service['options'])) {
                echo "    Options: " . json_encode($service['options']) . "\n";
            }
            echo "\n";
        }
    }

    // Test de la configuration
    echo "\n=== Test de la configuration ===\n";
    $config = config('msgraph.email-draft', []);
    echo "Configuration msgraph.email-draft:\n";
    if (empty($config)) {
        echo "❌ Configuration vide ou manquante\n";
    } else {
        foreach ($config as $className) {
            echo "  - {$className}\n";
            if (class_exists($className)) {
                echo "    ✓ Classe existe\n";
                if (method_exists($className, 'getKey')) {
                    echo "    ✓ Méthode getKey() existe: " . $className::getKey() . "\n";
                } else {
                    echo "    ❌ Méthode getKey() manquante\n";
                }
            } else {
                echo "    ❌ Classe n'existe pas\n";
            }
        }
    }

    echo "\n=== Test avec email-in ===\n";
    $servicesIn = EmailsProcessorRegisterServices::getAll('email-in');
    echo "Services email-in: " . count($servicesIn) . " détectés\n";
    foreach ($servicesIn as $key => $service) {
        echo "  - {$key}: {$service['label']}\n";
    }
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
