#!/bin/bash

# Script pour démarrer ngrok et mettre à jour automatiquement le .env
echo "Démarrage de ngrok..."

# Démarrer ngrok en arrière-plan
ngrok http https://x_filbreeze.test --host-header=x_filbreeze.test &
NGROK_PID=$!

# Attendre que ngrok démarre
sleep 5

# Récupérer l'URL ngrok via l'API locale
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url')

if [ "$NGROK_URL" != "null" ] && [ ! -z "$NGROK_URL" ]; then
    echo "URL ngrok détectée: $NGROK_URL"
    
    # Mettre à jour le .env
    sed -i "s|MSGRAPH_OAUTH_URL=.*|MSGRAPH_OAUTH_URL=${NGROK_URL}/connect|g" .env
    sed -i "s|MSGRAPH_LANDING_URL=.*|MSGRAPH_LANDING_URL=${NGROK_URL}/dashboard|g" .env
    
    echo "Fichier .env mis à jour avec l'URL: $NGROK_URL"
    echo "Webhook endpoint: ${NGROK_URL}/api/msgraph/webhook"
    
    # Créer ou mettre à jour automatiquement la subscription
    php artisan msgraph:create-subscription "${NGROK_URL}/api/msgraph/webhook"
    
else
    echo "Erreur: Impossible de récupérer l'URL ngrok"
    kill $NGROK_PID
    exit 1
fi

# Garder le script en vie
wait $NGROK_PID