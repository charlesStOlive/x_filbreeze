<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\DynamicEmailServicesCast;
use App\Services\MsGraph\MsGraphAuthService;
use App\Services\MsGraph\MsGraphSubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MsgUserDraft extends Model
{
    use HasFactory;

    protected $table = 'msg_user_drafts';
    protected $guarded = ['id'];
    protected $casts = [
        'data_email' => 'json'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge(
            $this->casts,
            DynamicEmailServicesCast::generateCasts('email-draft', 'options', 'services_options')
        );
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleted(function ($model) {
            $model->revokeSubscription();
        });
    }

    public function msg_email_drafts()
    {
        return $this->hasMany(MsgEmailDraft::class);
    }

    public static function getLocalUser()
    {
        return self::get()->pluck('email', 'id')->toArray();
    }

    public static function getLocalUserEmail()
    {
        return self::get()->pluck('email', 'email')->toArray();
    }

    /**
     * Récupère les utilisateurs via l'API et exclut les utilisateurs existants localement.
     */
    public static function getApiMsgUsersIdsEmails()
    {
        $authService = app(MsGraphAuthService::class);

        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $users = $authService->guzzle('get', 'users');
        $users = $users['value'] ?? [];

        $existingEmails = MsgUserDraft::pluck('email')->toArray();
        $filteredUsers = array_filter($users, function ($user) use ($existingEmails) {
            return isset($user['mail']) && !in_array($user['mail'], $existingEmails);
        });

        return \Arr::pluck($filteredUsers, 'mail', 'id');
    }

    public static function getApiMsgUser($id)
    {
        $authService = app(MsGraphAuthService::class);

        if ($authService->isConnected()) {
            $users = $authService->guzzle('get', 'users');
            $users = collect($users['value'] ?? []);
            return $users->where('id', $id)->first();
        }

        return [];
    }

    /**
     * Abonnement aux notifications de brouillon.
     */
    public function subscribe()
    {
        $authService = app(MsGraphAuthService::class);
        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);
        $response = $subscriptionService->subscribeToDraftNotifications($this->ms_id, $this->abn_secret);

        if ($response['id'] ?? false) {
            $this->subscription_id = $response['id'];
            $this->expire_at = Carbon::parse($response['expirationDateTime']);
            $this->save();
        } else {
            \Log::error($response);
        }
    }

    /**
     * Révocation de l'abonnement.
     */
    // public function revokeSubscription(string $sucription = null)
    // {
    //     if($sucription) {
    //         $this->subscription_id = $sucription;
    //     }
    //     if (!$this->subscription_id) {
    //         return;
    //     }
    //     $authService = app(MsGraphAuthService::class);
    //     if (!$authService->isConnected()) {
    //         $authService->connect(false);
    //     }

    //     $subscriptionService = app(MsGraphSubscriptionService::class);
    //     $response = $subscriptionService->unsubscribeFromDraftNotifications($this->subscription_id);

    //     if ($response['success'] ?? false) {
    //         $this->subscription_id = null;
    //         $this->expire_at = null;
    //         $this->save();
    //     } else {
    //         \Log::error($response);
    //     }
    // }

    public function revokeSubscription(string $sucription = null)
    {
        if($sucription) {
            $this->subscription_id = $sucription;
        }
        if (!$this->subscription_id) {
            return;
        }
        $authService = app(MsGraphAuthService::class);
        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);
        $response = $subscriptionService->revokeAllSubscriptionsForUser($this->ms_id);

        if ($response['success'] ?? false) {
            $this->subscription_id = null;
            $this->expire_at = null;
            $this->save();
        } else {
            \Log::error($response);
        }
    }

    /**
     * Renouvellement de l'abonnement.
     */
    public function refreshSubscription()
    {
        $authService = app(MsGraphAuthService::class);
        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);
        $response = $subscriptionService->renewDraftNotificationSubscription($this->subscription_id);

        if ($response['success'] ?? false) {
            $this->expire_at = Carbon::parse($response['response']['expirationDateTime']);
            $this->save();
        } else {
            \Log::error($response);
        }
    }
}
