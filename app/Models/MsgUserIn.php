<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\DynamicEmailServicesCast;
use App\Services\MsGraph\MsGraphAuthService;
use App\Services\MsGraph\MsGraphSubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MsgUserIn extends Model
{
    use HasFactory;

    protected $table = 'msg_user_ins';
    protected $guarded = ['id'];
    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge(
            $this->casts,
            DynamicEmailServicesCast::generateCasts('email-in', 'options', 'services_options')
        );
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleted(function ($model) {
            $model->msg_email_ins()->delete();
            $model->revokeSubscription();
        });
    }

    public function msg_email_ins()
    {
        return $this->hasMany(MsgEmailIn::class);
    }

    public static function getApiMsgUsersIdsEmails()
    {
        $authService = app(MsGraphAuthService::class);

        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        try {
            $users = $authService->guzzle('get', 'users');
        } catch (\Exception $e) {
            \Log::error('Failed to fetch users from MsGraph: ' . $e->getMessage());
            return [];
        }

        $users = $users['value'] ?? [];
        $existingEmails = self::pluck('email')->toArray();

        $filteredUsers = array_filter($users, function ($user) use ($existingEmails) {
            return isset($user['mail']) && !in_array($user['mail'], $existingEmails);
        });

        return \Arr::pluck($filteredUsers, 'mail', 'id');
    }

    public static function getLocalUser()
    {
        return self::pluck('email', 'id')->toArray();
    }

    public static function getLocalUserEmail()
    {
        return self::pluck('email', 'email')->toArray();
    }

    public static function getApiMsgUser($id)
    {
        $authService = app(MsGraphAuthService::class);

        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        try {
            $users = $authService->guzzle('get', 'users');
            $users = collect($users['value'] ?? []);
            return $users->where('id', $id)->first();
        } catch (\Exception $e) {
            \Log::error('Failed to fetch user from MsGraph: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Subscribe to email notifications.
     */
    public function subscribe()
    {
        $authService = app(MsGraphAuthService::class);
        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);
        $response = $subscriptionService->subscribeToEmailNotifications($this->ms_id, $this->abn_secret);

        if ($response['response']['id'] ?? false) {
            $this->subscription_id = $response['response']['id'];
            $this->expire_at = Carbon::parse($response['response']['expirationDateTime']);
            $this->save();
        } else {
            \Log::error('Failed to subscribe: ', $response);
        }
    }

    /**
     * Revoke email subscription.
     */
    public function revokeSubscription()
    {
        if (!$this->subscription_id) {
            \Log::info('No subscription ID found to revoke.');
            return;
        }
        $authService = app(MsGraphAuthService::class);
        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);
        $response = $subscriptionService->unsubscribeFromEmailNotifications($this->subscription_id);

        if ($response['success'] ?? false) {
            $this->subscription_id = null;
            $this->expire_at = null;
            $this->save();
        } else {
            \Log::error('Failed to revoke subscription.');
        }
    }

    /**
     * Refresh email subscription.
     */
    public function refreshSubscription()
    {
        if (!$this->subscription_id) {
            \Log::info('No subscription ID found to refresh.');
            return;
        }

        $authService = app(MsGraphAuthService::class);
        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);
        $response = $subscriptionService->renewEmailNotificationSubscription($this->subscription_id);

        if ($response['success'] ?? false) {
            $this->expire_at = Carbon::parse($response['response']['expirationDateTime']);
            $this->save();
        } else {
            \Log::error('Failed to refresh subscription.');
        }
    }
}
