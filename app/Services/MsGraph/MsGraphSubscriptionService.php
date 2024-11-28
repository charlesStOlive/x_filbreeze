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
        return $this->subscribe("users/{$userId}/mailFolders('Drafts')/messages", $secretClientValue, true);
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

    protected function subscribe(string $resource, string $clientState, bool $isDraft = false): array
    {
        $expirationDate = now()->addHours(24);

        $notificationUrl = $isDraft
            ? url('/api/email-draft-notifications')
            : url('/api/email-notifications');

        $subscription = [
            'changeType' => 'created,updated',
            'notificationUrl' => $notificationUrl,
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

    public function revokeAllSubscriptionsForUser(string $userId): array
    {
        try {
            // Récupérer toutes les subscriptions
            $subscriptions = $this->authService->guzzle('get', 'subscriptions');

            if (empty($subscriptions['value'])) {
                return ['success' => true, 'message' => 'No active subscriptions found for the user.'];
            }

            // Filtrer les subscriptions liées à l'utilisateur
            $userSubscriptions = array_filter($subscriptions['value'], function ($subscription) use ($userId) {
                return strpos($subscription['resource'], "users/{$userId}") === 0;
            });

            if (empty($userSubscriptions)) {
                return ['success' => true, 'message' => 'No subscriptions found for the specified user.'];
            }

            // Révoquer chaque subscription
            foreach ($userSubscriptions as $subscription) {
                $this->unsubscribe($subscription['id']);
            }

            return ['success' => true, 'message' => 'All subscriptions for the user have been revoked.'];
        } catch (\Exception $e) {
            \Log::error('Failed to revoke all subscriptions for user: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to revoke subscriptions.'];
        }
    }

}
