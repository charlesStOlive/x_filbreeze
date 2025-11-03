<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateMsGraphSubscription extends Command
{
    protected $signature = 'msgraph:create-subscription {webhook_url}';
    protected $description = 'Create a Microsoft Graph subscription for email notifications';

    public function handle()
    {
        $webhookUrl = $this->argument('webhook_url');

        // Obtenir un token d'accès
        $token = $this->getAccessToken();
        if (!$token) {
            $this->error('Failed to get access token');
            return 1;
        }

        // Créer la subscription (utiliser l'ID utilisateur spécifique)
        $response = Http::withToken($token)
            ->post('https://graph.microsoft.com/v1.0/subscriptions', [
                'changeType' => 'created,updated',
                'notificationUrl' => $webhookUrl,
                'resource' => 'users/cdbcf156-e86a-4c33-8d26-3697bfd06e1a/messages', // ID utilisateur de votre .env
                'expirationDateTime' => now()->addDays(3)->toISOString(), // Max 3 jours pour les emails
                'clientState' => 'SecretClientState' // Pour sécuriser les webhooks
            ]);

        if ($response->successful()) {
            $subscription = $response->json();
            $this->info('Subscription created successfully!');
            $this->info('Subscription ID: ' . $subscription['id']);
            $this->info('Expiration: ' . $subscription['expirationDateTime']);

            // Log the subscription details
            Log::info('Microsoft Graph subscription created', $subscription);

            return 0;
        } else {
            $this->error('Failed to create subscription');
            $this->error('Response: ' . $response->body());
            return 1;
        }
    }

    private function getAccessToken()
    {
        $response = Http::asForm()->post(config('msgraph.tenantUrlAccessToken'), [
            'client_id' => config('msgraph.clientId'),
            'client_secret' => config('msgraph.clientSecret'),
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials'
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        $this->error('Failed to get access token: ' . $response->body());
        return null;
    }
}
