<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Services email-draft de msgraph-filament : \n";
var_dump(config('msgraph-filament.email-draft'));

echo "\nServices email-draft de msgraph : \n";
var_dump(config('msgraph.email-draft'));

echo "\nTest avec email-in à la place : \n";
$processors = config('msgraph.email-in', []);
echo "Processors email-in trouvés : " . count($processors) . "\n";
foreach ($processors as $processor) {
    echo "- Classe: $processor\n";
    if (class_exists($processor)) {
        try {
            echo "  * Key: " . $processor::getKey() . "\n";
            echo "  * Label: " . $processor::getLabel() . "\n";
        } catch (Exception $e) {
            echo "  * Erreur: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  * Classe n'existe pas !\n";
    }
}

echo "\nServices email-in avec EmailsProcessorRegisterServices : \n";
try {
    $services = \App\Services\EmailsProcessorRegisterServices::getAll('email-in');
    foreach ($services as $key => $service) {
        echo "- $key: {$service['label']} (class: {$service['class']})\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
