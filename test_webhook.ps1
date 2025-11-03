# Test des Webhooks Microsoft Graph Email Drafts
# Version PowerShell pour Windows

Write-Host "=== Test des Webhooks Microsoft Graph Email Drafts ===" -ForegroundColor Blue
Write-Host ""

# Configuration
$BASE_URL = "http://x_filbreeze.test/api/email-draft-notifications"
$SUBSCRIPTION_ID = "test-subscription-12345"

Write-Host "1. Création d'un utilisateur de test..." -ForegroundColor Cyan

# Créer l'utilisateur de test via PHP
$createUserScript = @"
require_once 'vendor/autoload.php';
`$app = require_once 'bootstrap/app.php';
`$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

`$testUser = \App\Models\MsgUserDraft::updateOrCreate(
    ['email' => 'test-webhook@example.com'],
    [
        'ms_id' => 'test-ms-id-' . uniqid(),
        'subscription_id' => '$SUBSCRIPTION_ID',
        'expire_at' => now()->addDays(3),
        'is_test' => true,
        'services_options' => [
            'd-cor' => [
                'mode' => 'actif',
                'agent_id' => 'ag:3e2c948d:20241122:correction-ortho-de-mails:2bf76447',
                'regex_code' => 'corrige',
                'create_new_draft' => true
            ],
            'd-trad' => [
                'mode' => 'inactif'
            ]
        ]
    ]
);

echo \"✅ Utilisateur créé/mis à jour - ID: {`$testUser->id}\n\";
echo \"📧 Email: {`$testUser->email}\n\";
echo \"🔑 Subscription: {`$testUser->subscription_id}\n\";
echo \"📊 Config services: \" . json_encode(`$testUser->services_options, JSON_PRETTY_PRINT) . \"\n\";
"@

php -r $createUserScript

Write-Host ""
Write-Host "2. Test 1: Email avec code regex 'corrige'" -ForegroundColor Cyan

# Payload pour email avec code
$timestamp = [int][double]::Parse((Get-Date -UFormat %s))
$payloadWithCode = @{
    value = @(
        @{
            clientState = $SUBSCRIPTION_ID
            tenantId = "test-tenant-id"
            resourceData = @{
                id = "email-with-code-$timestamp"
            }
        }
    )
} | ConvertTo-Json -Depth 3

Write-Host "📤 Envoi de la requête webhook..."
Write-Host "🌐 URL: $BASE_URL"
Write-Host "📋 Payload: $payloadWithCode"
Write-Host ""

try {
    $headers = @{
        'Content-Type' = 'application/json'
        'Accept' = 'application/json'
    }
    
    $response1 = Invoke-RestMethod -Uri $BASE_URL -Method Post -Body $payloadWithCode -Headers $headers
    Write-Host "✅ Test 1 réussi" -ForegroundColor Green
    Write-Host "📥 Réponse: $($response1 | ConvertTo-Json -Compress)"
}
catch {
    Write-Host "❌ Test 1 échoué" -ForegroundColor Red
    Write-Host "📥 Erreur: $($_.Exception.Message)"
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "📥 Réponse: $responseBody"
    }
}

Write-Host ""
Write-Host "3. Test 2: Email sans code regex" -ForegroundColor Cyan

# Payload pour email sans code
$timestamp2 = [int][double]::Parse((Get-Date -UFormat %s))
$payloadWithoutCode = @{
    value = @(
        @{
            clientState = $SUBSCRIPTION_ID
            tenantId = "test-tenant-id"
            resourceData = @{
                id = "email-without-code-$timestamp2"
            }
        }
    )
} | ConvertTo-Json -Depth 3

Write-Host "📤 Envoi de la requête webhook..."
Write-Host "📋 Payload: $payloadWithoutCode"
Write-Host ""

try {
    $response2 = Invoke-RestMethod -Uri $BASE_URL -Method Post -Body $payloadWithoutCode -Headers $headers
    Write-Host "✅ Test 2 réussi" -ForegroundColor Green
    Write-Host "📥 Réponse: $($response2 | ConvertTo-Json -Compress)"
}
catch {
    Write-Host "❌ Test 2 échoué" -ForegroundColor Red
    Write-Host "📥 Erreur: $($_.Exception.Message)"
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "📥 Réponse: $responseBody"
    }
}

Write-Host ""
Write-Host "4. Vérification des résultats dans la base de données" -ForegroundColor Cyan

$checkDbScript = @"
require_once 'vendor/autoload.php';
`$app = require_once 'bootstrap/app.php';
`$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"📊 Vérification des email drafts créés...\n\";

`$recentDrafts = \App\Models\MsgEmailDraft::where('created_at', '>=', now()->subMinutes(5))
    ->orderBy('created_at', 'desc')
    ->get();

if (`$recentDrafts->count() > 0) {
    echo \"✅ {`$recentDrafts->count()} email draft(s) trouvé(s):\n\";
    foreach (`$recentDrafts as `$draft) {
        echo \"   📧 ID: {`$draft->id} | Email ID: {`$draft->email_id} | Status: {`$draft->status}\n\";
        if (`$draft->services_options) {
            echo \"   📊 Services: \" . json_encode(`$draft->services_options, JSON_PRETTY_PRINT) . \"\n\";
        }
        echo \"\n\";
    }
} else {
    echo \"⚠️  Aucun email draft récent trouvé\n\";
}

// Vérifier les jobs en queue
`$queuedJobs = \DB::table('jobs')->where('created_at', '>=', now()->subMinutes(5))->count();
echo \"🔄 Jobs en queue: `$queuedJobs\n\";
"@

php -r $checkDbScript

Write-Host ""
Write-Host "=== Résumé des tests ===" -ForegroundColor Blue

Write-Host "💡 Pour debug supplémentaire:"
Write-Host "   - Logs Laravel: Get-Content storage/logs/laravel.log -Tail 50"
Write-Host "   - Vérifier la queue: php artisan queue:work --once"
Write-Host "   - Inspecter la DB: php artisan tinker"

Write-Host ""
Write-Host "=== Fin des tests ===" -ForegroundColor Green