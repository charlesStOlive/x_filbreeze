<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use CharlesStOlive\MsGraphFilament\Livewire\MailServiceCell;

echo "=== TEST COMPOSANT AVEC VUE DU PLUGIN ===\n\n";

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
    
    // 3. Tester le rendu avec la nouvelle vue
    $view = $component->render();
    echo "✅ Vue: " . $view->getName() . "\n";
    
    // 4. Vérifier que la vue du plugin existe
    $viewPath = 'msgraph-filament::mail-service-cell';
    if (view()->exists($viewPath)) {
        echo "✅ Vue du plugin trouvée: $viewPath\n";
    } else {
        echo "❌ Vue du plugin non trouvée: $viewPath\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST ===\n";