<?php 

namespace App\Services\MsGraph;

use App\Services\MsGraph\MsGraphAuthService;

class MsGraphSubscriptionService
{
    protected MsGraphAuthService $authService;

    public function __construct(MsGraphAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function subscribeToDraftNotifications(string $userId, string $secretClientValue): array
    {
        return $this->subscribe("users/{$userId}/mailFolders('Drafts')/messages", $secretClientValue);
    }

    public function subscribeToEmailNotifications(string $userId, string $secretClientValue): array
    {
        return $this->subscribe("users/{$userId}/messages", $secretClientValue);
    }

    public function unsubscribeFromDraftNotifications(string $subscriptionId): array
    {
        return $this->unsubscribe($subscriptionId);
    }

    public function unsubscribeFromEmailNotifications(string $subscriptionId): array
    {
        return $this->unsubscribe($subscriptionId);
    }

    public function renewDraftNotificationSubscription(string $subscriptionId): array
    {
        return $this->renewSubscription($subscriptionId);
    }

    public function renewEmailNotificationSubscription(string $subscriptionId): array
    {
        return $this->renewSubscription($subscriptionId);
    }

    protected function subscribe(string $resource, string $clientState): array
    {
        $expirationDate = now()->addHours(24);

        $subscription = [
            'changeType' => 'created,updated',
            'notificationUrl' => url('/api/notifications'),
            'resource' => $resource,
            'expirationDateTime' => $expirationDate->toISOString(),
            'clientState' => $clientState,
        ];

        return $this->authService->guzzle('post', 'subscriptions', $subscription);
    }

    protected function unsubscribe(string $subscriptionId): array
    {
        return $this->authService->guzzle('delete', "subscriptions/{$subscriptionId}");
    }

    protected function renewSubscription(string $subscriptionId): array
    {
        $subscription = [
            'expirationDateTime' => now()->addHours(26)->toISOString(),
        ];

        return $this->authService->guzzle('patch', "subscriptions/{$subscriptionId}", $subscription);
    }
}
