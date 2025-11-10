<?php

require_once __DIR__ . '/vendor/autoload.php';

use CharlesStOlive\MsGraphFilament\Infrastructure\MsGraph\GraphSubscriptionService;
use CharlesStOlive\MsGraphFilament\Infrastructure\MsGraph\GraphAuthService;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧹 Cleaning up old MS Graph subscriptions...\n\n";

// Subscriptions à supprimer
$subscriptionsToDelete = [
    '383be370-82f1-4b23-b923-396a4f34ed68',
    '133be7aa-6d16-48c4-8abe-bd6007bbf77a'
];

$authService = app(GraphAuthService::class);
$subscriptionService = app(GraphSubscriptionService::class);

echo "🔑 Authentication...\n";
$token = $authService->getToken();
if (!$token) {
    echo "❌ Failed to get auth token\n";
    exit(1);
}

echo "✅ Token obtained\n\n";

foreach ($subscriptionsToDelete as $subscriptionId) {
    echo "🗑️ Deleting subscription: $subscriptionId\n";

    try {
        $response = $subscriptionService->deleteSubscription($subscriptionId);
        if ($response['success']) {
            echo "✅ Successfully deleted subscription: $subscriptionId\n";
        } else {
            echo "❌ Failed to delete subscription: $subscriptionId\n";
            echo "   Reason: " . ($response['message'] ?? 'Unknown error') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Exception while deleting subscription: $subscriptionId\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "🎉 Cleanup complete!\n";
