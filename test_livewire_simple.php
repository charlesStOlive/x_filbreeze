<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEST ENREGISTREMENT LIVEWIRE (simplifié) ===\n\n";

// 1. Vérifier que la classe existe
$className = 'CharlesStOlive\MsGraphFilament\Livewire\MailServiceCell';
if (class_exists($className)) {
    echo "✅ Classe $className existe\n";
} else {
    echo "❌ Classe $className n'existe pas\n";
}

// 2. Tester l'enregistrement des composants
$testNames = ['tables.mail-service-cell', 'mail-service-cell'];

foreach ($testNames as $name) {
    try {
        $component = \Livewire\Livewire::getComponent($name);
        echo "✅ Composant '$name' trouvé: " . get_class($component) . "\n";
    } catch (Exception $e) {
        echo "❌ Composant '$name' non trouvé: " . $e->getMessage() . "\n";
    }
}

// 3. Essayer de créer une instance du composant
try {
    echo "\nTest de création d'instance...\n";
    $instance = new $className();
    echo "✅ Instance créée avec succès\n";
} catch (Exception $e) {
    echo "❌ Erreur lors de la création d'instance: " . $e->getMessage() . "\n";
}

echo "\n=== FIN TEST ===\n";
