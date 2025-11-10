<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use Illuminate\Support\Facades\Log;

echo "🔍 Testing MS Graph Subscription...\n\n";

// Configuration actuelle
echo "📋 Configuration:\n";
echo "- WEBHOOK_BASE_URL: " . env('WEBHOOK_BASE_URL', 'NOT SET') . "\n";
echo "- APP_URL: " . env('APP_URL', 'NOT SET') . "\n";
echo "- Config webhook_base_url: " . config('msgraph.webhook_base_url', 'NULL') . "\n\n";

// Récupérer le premier utilisateur draft pour test
$user = MsgUserDraft::first();

if (!$user) {
    echo "❌ Aucun utilisateur MsgUserDraft trouvé. Créez d'abord un utilisateur.\n";
    exit(1);
}

echo "👤 Utilisateur de test:\n";
echo "- Email: {$user->email}\n";
echo "- MS ID: {$user->ms_id}\n";
echo "- Secret: {$user->abn_secret}\n\n";

echo "🚀 Lancement de la souscription...\n";
echo "📝 Vérifiez les logs dans storage/logs/laravel.log\n\n";

try {
    $user->subscribe();
    echo "✅ Souscription terminée (voir logs pour détails)\n";
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n🔍 Dernières entrées du log:\n";
echo "📁 Consultez: storage/logs/laravel.log\n";
