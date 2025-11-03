#!/bin/bash

echo "=== Test des Webhooks Microsoft Graph Email Drafts ==="
echo ""

# Configuration
BASE_URL="http://x_filbreeze.test/api/email-draft-notifications"
SUBSCRIPTION_ID="test-subscription-12345"

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}1. Création d'un utilisateur de test...${NC}"

# Créer l'utilisateur de test via PHP
php -r "
require_once 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$testUser = \App\Models\MsgUserDraft::updateOrCreate(
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

echo \"✅ Utilisateur créé/mis à jour - ID: {\$testUser->id}\n\";
echo \"📧 Email: {\$testUser->email}\n\";
echo \"🔑 Subscription: {\$testUser->subscription_id}\n\";
echo \"📊 Config services: \" . json_encode(\$testUser->services_options, JSON_PRETTY_PRINT) . \"\n\";
"

echo ""
echo -e "${BLUE}2. Test 1: Email avec code regex 'corrige'${NC}"

# Payload pour email avec code
PAYLOAD_WITH_CODE='{
    "value": [
        {
            "clientState": "'$SUBSCRIPTION_ID'",
            "tenantId": "test-tenant-id",
            "resourceData": {
                "id": "email-with-code-'$(date +%s)'"
            }
        }
    ]
}'

echo "📤 Envoi de la requête webhook..."
echo "🌐 URL: $BASE_URL"
echo "📋 Payload: $PAYLOAD_WITH_CODE"
echo ""

RESPONSE_1=$(curl -s -w "HTTP_STATUS:%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "$PAYLOAD_WITH_CODE" \
    "$BASE_URL")

HTTP_STATUS_1=$(echo $RESPONSE_1 | tr -d '\n' | sed -e 's/.*HTTP_STATUS://')
RESPONSE_BODY_1=$(echo $RESPONSE_1 | sed -e 's/HTTP_STATUS:.*//g')

if [ "$HTTP_STATUS_1" = "200" ]; then
    echo -e "${GREEN}✅ Test 1 réussi (HTTP $HTTP_STATUS_1)${NC}"
    echo "📥 Réponse: $RESPONSE_BODY_1"
else
    echo -e "${RED}❌ Test 1 échoué (HTTP $HTTP_STATUS_1)${NC}"
    echo "📥 Réponse: $RESPONSE_BODY_1"
fi

echo ""
echo -e "${BLUE}3. Test 2: Email sans code regex${NC}"

# Payload pour email sans code
PAYLOAD_WITHOUT_CODE='{
    "value": [
        {
            "clientState": "'$SUBSCRIPTION_ID'",
            "tenantId": "test-tenant-id",
            "resourceData": {
                "id": "email-without-code-'$(date +%s)'"
            }
        }
    ]
}'

echo "📤 Envoi de la requête webhook..."
echo "📋 Payload: $PAYLOAD_WITHOUT_CODE"
echo ""

RESPONSE_2=$(curl -s -w "HTTP_STATUS:%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "$PAYLOAD_WITHOUT_CODE" \
    "$BASE_URL")

HTTP_STATUS_2=$(echo $RESPONSE_2 | tr -d '\n' | sed -e 's/.*HTTP_STATUS://')
RESPONSE_BODY_2=$(echo $RESPONSE_2 | sed -e 's/HTTP_STATUS:.*//g')

if [ "$HTTP_STATUS_2" = "200" ]; then
    echo -e "${GREEN}✅ Test 2 réussi (HTTP $HTTP_STATUS_2)${NC}"
    echo "📥 Réponse: $RESPONSE_BODY_2"
else
    echo -e "${RED}❌ Test 2 échoué (HTTP $HTTP_STATUS_2)${NC}"
    echo "📥 Réponse: $RESPONSE_BODY_2"
fi

echo ""
echo -e "${BLUE}4. Vérification des résultats dans la base de données${NC}"

php -r "
require_once 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"📊 Vérification des email drafts créés...\n\";

\$recentDrafts = \App\Models\MsgEmailDraft::where('created_at', '>=', now()->subMinutes(5))
    ->orderBy('created_at', 'desc')
    ->get();

if (\$recentDrafts->count() > 0) {
    echo \"✅ {\$recentDrafts->count()} email draft(s) trouvé(s):\n\";
    foreach (\$recentDrafts as \$draft) {
        echo \"   📧 ID: {\$draft->id} | Email ID: {\$draft->email_id} | Status: {\$draft->status}\n\";
        if (\$draft->services_options) {
            echo \"   📊 Services: \" . json_encode(\$draft->services_options, JSON_PRETTY_PRINT) . \"\n\";
        }
        echo \"\n\";
    }
} else {
    echo \"⚠️  Aucun email draft récent trouvé\n\";
}

// Vérifier les jobs en queue
\$queuedJobs = \DB::table('jobs')->where('created_at', '>=', now()->subMinutes(5))->count();
echo \"🔄 Jobs en queue: \$queuedJobs\n\";
"

echo ""
echo -e "${BLUE}=== Résumé des tests ===${NC}"

TOTAL_TESTS=2
SUCCESSFUL_TESTS=0

if [ "$HTTP_STATUS_1" = "200" ]; then
    SUCCESSFUL_TESTS=$((SUCCESSFUL_TESTS + 1))
fi

if [ "$HTTP_STATUS_2" = "200" ]; then
    SUCCESSFUL_TESTS=$((SUCCESSFUL_TESTS + 1))
fi

echo "📊 Tests réussis: $SUCCESSFUL_TESTS/$TOTAL_TESTS"

if [ $SUCCESSFUL_TESTS -eq $TOTAL_TESTS ]; then
    echo -e "${GREEN}🎉 Tous les tests ont réussi !${NC}"
else
    echo -e "${YELLOW}⚠️  Certains tests ont échoué. Vérifiez les logs Laravel.${NC}"
fi

echo ""
echo "💡 Pour debug supplémentaire:"
echo "   - Logs Laravel: tail -f storage/logs/laravel.log"
echo "   - Vérifier la queue: php artisan queue:work --once"
echo "   - Inspecter la DB: php artisan tinker"

echo ""
echo -e "${GREEN}=== Fin des tests ===${NC}"