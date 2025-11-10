<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use CharlesStOlive\MsGraphFilament\Livewire\MailServiceCell;

echo "=== TEST COMPOSANT SIMPLIFIÉ ===\n\n";

// 1. Récupérer un record de test
$record = MsgUserDraft::first();
if (!$record) {
    echo "Aucun record trouvé\n";
    exit;
}

// 2. Tester le composant
try {
    $component = new MailServiceCell();
    $component->mount($record, 'email-draft', 'view');
    
    echo "✅ Composant créé avec succès!\n";
    echo "Nombre de services: " . count($component->servicesData) . "\n";
    
    foreach ($component->servicesData as $service) {
        echo "  - " . $service['key'] . ": " . $service['label'] . " (mode: " . $service['mode'] . ")\n";
    }
    
    // 3. Tester le rendu
    $view = $component->render();
    echo "✅ Vue: " . $view->getName() . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== FIN TEST ===\n";