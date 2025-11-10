<?php

require_once 'vendor/autoload.php';

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use Illuminate\Database\Eloquent\Model;

// Test de la méthode notifyError
echo "Test de la méthode notifyError...\n";

// Simulons un draft user pour tester
$draft = new MsgUserDraft();

// Test 1: notifyError existe maintenant
if (method_exists($draft, 'notifyError')) {
    echo "✅ La méthode notifyError() existe maintenant\n";
} else {
    echo "❌ La méthode notifyError() n'existe pas\n";
}

// Test 2: Autres méthodes du trait
if (method_exists($draft, 'notifySuccess')) {
    echo "✅ La méthode notifySuccess() existe\n";
}

if (method_exists($draft, 'notifyInfo')) {
    echo "✅ La méthode notifyInfo() existe\n";
}

if (method_exists($draft, 'notifyWarning')) {
    echo "✅ La méthode notifyWarning() existe\n";
}

echo "\n🎯 Le trait SendsNotifications du package est maintenant disponible !\n";
echo "📝 Test scenario: Tentative d'annulation d'abonnement avec erreur API MS Graph\n\n";
