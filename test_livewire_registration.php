<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEST ENREGISTREMENT LIVEWIRE ===\n\n";

// 1. Vérifier si le composant Livewire est enregistré
$manager = app(\Livewire\LivewireManager::class);

echo "Composants Livewire enregistrés:\n";
$components = $manager->getComponentManifest();

foreach ($components as $name => $class) {
    if (str_contains($name, 'mail-service')) {
        echo "  - $name => $class\n";
    }
}

// 2. Tester l'enregistrement spécifique
$testNames = ['tables.mail-service-cell', 'mail-service-cell'];

foreach ($testNames as $name) {
    try {
        $component = $manager->getComponent($name);
        echo "\n✅ Composant '$name' trouvé: " . get_class($component) . "\n";
    } catch (Exception $e) {
        echo "\n❌ Composant '$name' non trouvé: " . $e->getMessage() . "\n";
    }
}

// 3. Vérifier que la classe existe
$className = 'CharlesStOlive\MsGraphFilament\Livewire\MailServiceCell';
if (class_exists($className)) {
    echo "\n✅ Classe $className existe\n";
} else {
    echo "\n❌ Classe $className n'existe pas\n";
}

echo "\n=== FIN TEST ===\n";
