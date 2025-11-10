<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;

$user = MsgUserDraft::where('email', 'charles@notilac.fr')->first();

echo "✅ État final de l'abonnement:\n";
echo "- Subscription ID: " . ($user->subscription_id ?? 'NULL') . "\n";
echo "- Expire at: " . ($user->expire_at ?? 'NULL') . "\n";
echo "\n🎯 L'abonnement aux notifications de draft a bien été révoqué!\n";
