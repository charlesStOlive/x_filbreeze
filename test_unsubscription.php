<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use Illuminate\Support\Facades\Log;

echo "🔍 Testing MS Graph Unsubscription...\n\n";

// ID de souscription depuis les logs
$subscriptionId = 'd27bee44-1397-407d-a7e5-2258d4082623';

// Récupérer l'utilisateur qui a cette souscription
$user = MsgUserDraft::where('subscription_id', $subscriptionId)->first();

if (!$user) {
    echo "❌ Aucun utilisateur trouvé avec cette subscription_id. Recherche par email...\n";
    $user = MsgUserDraft::where('email', 'charles@notilac.fr')->first();
    if ($user) {
        echo "✅ Utilisateur trouvé par email, mise à jour de subscription_id...\n";
        $user->subscription_id = $subscriptionId;
        $user->save();
    } else {
        echo "❌ Aucun utilisateur trouvé.\n";
        exit(1);
    }
}

echo "👤 Utilisateur trouvé:\n";
echo "- Email: {$user->email}\n";
echo "- Subscription ID: {$user->subscription_id}\n\n";

echo "🚫 Lancement de la révocation...\n";
echo "📝 Vérifiez les logs dans storage/logs/laravel.log\n\n";

try {
    $user->revokeSubscription();
    echo "✅ Révocation terminée (voir logs pour détails)\n";

    // Vérifier l'état après révocation
    $user->refresh();
    echo "\n📊 État après révocation:\n";
    echo "- Subscription ID: " . ($user->subscription_id ?? 'NULL') . "\n";
    echo "- Expire at: " . ($user->expire_at ?? 'NULL') . "\n";
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
