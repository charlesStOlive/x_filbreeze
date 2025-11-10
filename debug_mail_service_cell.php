<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use CharlesStOlive\MsGraphFilament\Services\EmailsProcessorRegisterServices;

echo "=== DEBUG MAIL SERVICE CELL ===\n\n";

// 1. Tester le service d'enregistrement
echo "1. Test EmailsProcessorRegisterServices::getAll('email-draft'):\n";
$services = EmailsProcessorRegisterServices::getAll('email-draft');
echo "Nombre de services trouvés: " . count($services) . "\n";
foreach ($services as $key => $service) {
    echo "  - $key: " . $service['label'] . " (" . $service['class'] . ")\n";
}
echo "\n";

// 2. Récupérer un record de test
echo "2. Récupération d'un record MsgUserDraft:\n";
$record = MsgUserDraft::first();
if (!$record) {
    echo "Aucun record MsgUserDraft trouvé. Création d'un record de test...\n";
    $record = new MsgUserDraft();
    $record->save();
}
echo "Record ID: " . $record->id . "\n";
echo "Record services_options: " . json_encode($record->services_options) . "\n";
echo "\n";

// 3. Simuler le comportement du composant MailServiceCell
echo "3. Simulation du comportement MailServiceCell::rebuildServicesData():\n";

$serviceType = 'email-draft';
$openMode = 'view';
$servicesData = [];

// Code simulé de rebuildServicesData()
$services = EmailsProcessorRegisterServices::getAll($serviceType);
echo "Services récupérés dans rebuildServicesData: " . count($services) . "\n";

$modeSuffixes = [
    'edit' => ' à éditer',
    'view' => ' à Voir',
    'results' => ' à Résultats',
];

foreach ($services as $serviceKey => $serviceInfo) {
    echo "  Traitement du service: $serviceKey\n";

    if ($openMode === 'results') {
        $results = $record->services_results[$serviceKey] ?? [];
        if (empty($results)) {
            echo "    Pas de résultats pour $serviceKey, service ignoré\n";
            continue;
        }
    }

    $color = 'gray';
    $icon = 'heroicon-o-cog';
    $message = '';

    // Code de détermination du style (simplifié)
    $servicesData[$serviceKey] = [
        'key' => $serviceKey,
        'label' => $serviceInfo['label'],
        'color' => $color,
        'icon' => $icon,
        'message' => $message,
        'class' => $serviceInfo['class'],
        'description' => $serviceInfo['description'] ?? '',
    ];

    echo "    Service ajouté: " . $serviceInfo['label'] . "\n";
}

echo "\nServicesData final: " . count($servicesData) . " services\n";
foreach ($servicesData as $key => $data) {
    echo "  - $key: " . $data['label'] . "\n";
}

// 4. Test du rendu du composant (simulation)
echo "\n4. Test du rendu du composant:\n";
if (empty($servicesData)) {
    echo "PROBLÈME: servicesData est vide - 'Aucun service détecté' sera affiché\n";
} else {
    echo "OK: servicesData contient " . count($servicesData) . " services\n";
}

echo "\n=== FIN DEBUG ===\n";
