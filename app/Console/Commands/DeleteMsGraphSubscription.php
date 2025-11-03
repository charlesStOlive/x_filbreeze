<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DeleteMsGraphSubscription extends Command
{
    protected $signature = 'msgraph:delete-subscription {subscription_id}';
    protected $description = 'Delete a Microsoft Graph subscription';

    public function handle()
    {
        $subscriptionId = $this->argument('subscription_id');

        // Obtenir un token d'accès
        $token = $this->getAccessToken();
        if (!$token) {
            $this->error('Failed to get access token');
            return 1;
        }

        // Supprimer la subscription
        $response = Http::withToken($token)
            ->delete("https://graph.microsoft.com/v1.0/subscriptions/{$subscriptionId}");

        if ($response->successful()) {
            $this->info('Subscription deleted successfully!');
            return 0;
        } else {
            $this->error('Failed to delete subscription');
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
