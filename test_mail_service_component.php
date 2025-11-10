<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use CharlesStOlive\MsGraphFilament\Livewire\MailServiceCell;

echo "=== TEST DU COMPOSANT MAIL SERVICE CELL ===\n\n";

// 1. Récupérer un record de test
$record = MsgUserDraft::first();
if (!$record) {
    echo "Aucun record trouvé, création d'un record de test...\n";
    $record = new MsgUserDraft();
    $record->save();
}

echo "Record ID: " . $record->id . "\n";

// 2. Créer une instance du composant
echo "Création du composant MailServiceCell...\n";

try {
    $component = new MailServiceCell();
    $component->mount($record, 'email-draft', 'view');

    echo "Composant créé avec succès!\n";
    echo "Service Type: " . $component->serviceType . "\n";
    echo "Open Mode: " . $component->openMode . "\n";
    echo "Nombre de services dans servicesData: " . count($component->servicesData) . "\n";

    foreach ($component->servicesData as $service) {
        echo "  - " . $service['key'] . ": " . $service['label'] . "\n";
        echo "    Mode: " . $service['mode'] . "\n";
        echo "    Background: " . $service['background_color'] . "\n";
        echo "    Border: " . $service['border_color'] . "\n";
    }

    // 3. Tester le rendu
    echo "\nTest du rendu...\n";
    $view = $component->render();
    echo "View name: " . $view->getName() . "\n";
    echo "Le template blade existe: " . (file_exists(resource_path('views/livewire/tables/mail-service-cell.blade.php')) ? 'OUI' : 'NON') . "\n";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST ===\n";
