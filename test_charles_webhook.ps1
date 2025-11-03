# Test Webhook Microsoft Graph - Script PowerShell Simple
# Pour l'utilisateur charles@notilac.fr

Write-Host "=== Test Webhook Microsoft Graph - charles@notilac.fr ===" -ForegroundColor Blue
Write-Host ""

# Configuration
$BASE_URL = "http://x_filbreeze.test/api/email-draft-notifications"
$CLIENT_STATE = "a477fcf7-cd54-4010-b5dc-fa224129b1ca"  # abn_secret de l'utilisateur
$TENANT_ID = "9826d9ed-97c7-4e65-b6ef-1a7196fe347a"     # Votre vrai tenant ID Azure

Write-Host "Configuration:" -ForegroundColor Cyan
Write-Host "  URL: $BASE_URL"
Write-Host "  Client State: $CLIENT_STATE"
Write-Host "  Tenant ID: $TENANT_ID"
Write-Host "  Utilisateur: charles@notilac.fr"
Write-Host ""

# Test 1: Email avec le mot "corrige" dans le sujet (devrait déclencher le service)
Write-Host "Test 1: Email de brouillon avec code 'corrige'" -ForegroundColor Yellow

$timestamp1 = [int][double]::Parse((Get-Date -UFormat %s))
$emailId1 = "AAMkAGE1M2IyNGNmLTI5MTktNDUyZi1WITH-CODE-$timestamp1"

$payload1 = @{
    value = @(
        @{
            subscriptionId = "subscription-id-draft"
            clientState = $CLIENT_STATE
            changeType = "created"
            resource = "me/mailFolders/drafts/messages/$emailId1"
            resourceData = @{
                '@odata.type' = "#Microsoft.Graph.Message"
                '@odata.id' = "Users/charles@notilac.fr/Messages/$emailId1"
                id = $emailId1
            }
            subscriptionExpirationDateTime = "2025-11-01T18:23:45.9356913Z"
            tenantId = $TENANT_ID
        }
    )
} | ConvertTo-Json -Depth 4

Write-Host "📧 Email ID: $emailId1"
Write-Host "📤 Envoi du webhook..."

try {
    $headers = @{
        'Content-Type' = 'application/json'
        'Accept' = 'application/json'
    }
    
    $response1 = Invoke-RestMethod -Uri $BASE_URL -Method Post -Body $payload1 -Headers $headers -TimeoutSec 30
    Write-Host "✅ Test 1 réussi" -ForegroundColor Green
    Write-Host "📥 Réponse: $($response1 | ConvertTo-Json -Compress)"
}
catch {
    Write-Host "❌ Test 1 échoué" -ForegroundColor Red
    Write-Host "📥 Erreur: $($_.Exception.Message)"
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "📥 Détail: $responseBody"
    }
}

Write-Host ""

# Test 2: Email normal sans code spécial (ne devrait pas déclencher le service)
Write-Host "Test 2: Email de brouillon normal" -ForegroundColor Yellow

$timestamp2 = [int][double]::Parse((Get-Date -UFormat %s))
$emailId2 = "AAMkAGE1M2IyNGNmLTI5MTktNDUyZi1WITHOUT-CODE-$timestamp2" 

$payload2 = @{
    value = @(
        @{
            subscriptionId = "subscription-id-draft"
            clientState = $CLIENT_STATE
            changeType = "created"
            resource = "me/mailFolders/drafts/messages/$emailId2"
            resourceData = @{
                '@odata.type' = "#Microsoft.Graph.Message"
                '@odata.id' = "Users/charles@notilac.fr/Messages/$emailId2"
                id = $emailId2
            }
            subscriptionExpirationDateTime = "2025-11-01T18:23:45.9356913Z"
            tenantId = $TENANT_ID
        }
    )
} | ConvertTo-Json -Depth 4

Write-Host "📧 Email ID: $emailId2"
Write-Host "📤 Envoi du webhook..."

try {
    $response2 = Invoke-RestMethod -Uri $BASE_URL -Method Post -Body $payload2 -Headers $headers -TimeoutSec 30
    Write-Host "✅ Test 2 réussi" -ForegroundColor Green
    Write-Host "📥 Réponse: $($response2 | ConvertTo-Json -Compress)"
}
catch {
    Write-Host "❌ Test 2 échoué" -ForegroundColor Red
    Write-Host "📥 Erreur: $($_.Exception.Message)"
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "📥 Détail: $responseBody"
    }
}

Write-Host ""

# Vérification en base de données
Write-Host "Vérification des résultats en base de données..." -ForegroundColor Cyan

$checkScript = @"
require_once 'vendor/autoload.php';
`$app = require_once 'bootstrap/app.php';
`$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"📊 Emails drafts créés dans les 5 dernières minutes :\n\";
`$recentDrafts = \App\Models\MsgEmailDraft::where('created_at', '>=', now()->subMinutes(5))
    ->orderBy('created_at', 'desc')
    ->get();

if (`$recentDrafts->count() > 0) {
    foreach (`$recentDrafts as `$draft) {
        echo \"   📧 ID: {`$draft->id} | Email ID: {`$draft->email_id}\n\";
        echo \"   📊 Status: {`$draft->status}\n\";
        if (`$draft->services_options) {
            echo \"   ⚙️  Services: \" . json_encode(`$draft->services_options) . \"\n\";
        }
        echo \"\n\";
    }
} else {
    echo \"   ⚠️  Aucun draft récent trouvé\n\";
}

// Vérifier les jobs
`$jobs = \DB::table('jobs')->where('created_at', '>=', now()->subMinutes(5))->count();
echo \"🔄 Jobs en queue récents: `$jobs\n\";
"@

try {
    $dbResult = php -r $checkScript
    Write-Host $dbResult
}
catch {
    Write-Host "⚠️  Impossible de vérifier la base de données" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=== Instructions pour debug ===" -ForegroundColor Blue
Write-Host "1. Vérifier les logs Laravel:"
Write-Host "   Get-Content storage\logs\laravel.log -Tail 20"
Write-Host ""
Write-Host "2. Traiter les jobs en queue:"
Write-Host "   php artisan queue:work --once"
Write-Host ""
Write-Host "3. Inspecter la base de données:"
Write-Host "   php artisan tinker"
Write-Host "   App\Models\MsgEmailDraft::latest()->take(5)->get()"
Write-Host ""
Write-Host "=== Fin des tests ===" -ForegroundColor Green