<?php

namespace App\Models;

use Exception;
use Log;
use Arr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\DynamicEmailServicesCast;
use App\Infrastructure\MsGraph\GraphAuthService;
use App\Services\MsGraph\MsGraphSubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class MsgUserIn extends Model
{
    use HasFactory;

    protected $table = 'msg_user_ins';
    protected $guarded = ['id'];
    protected $casts = [
        'expire_at' => 'datetime',
        'services_options' => 'json',
        'services_results' => 'json',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Les casts dynamiques ne sont plus nécessaires avec la nouvelle approche
        // Les données JSON sont maintenant gérées directement via les méthodes helper
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
        $authService = app(GraphAuthService::class);

        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        try {
            $users = $authService->guzzle('get', 'users');
        } catch (Exception $e) {
            Log::error('Failed to fetch users from MsGraph: ' . $e->getMessage());
            return [];
        }

        $users = $users['value'] ?? [];
        $existingEmails = self::pluck('email')->toArray();

        $filteredUsers = array_filter($users, function ($user) use ($existingEmails) {
            return isset($user['mail']) && !in_array($user['mail'], $existingEmails);
        });

        return Arr::pluck($filteredUsers, 'mail', 'id');
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
        $authService = app(GraphAuthService::class);

        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        try {
            $users = $authService->guzzle('get', 'users');
            $users = collect($users['value'] ?? []);
            return $users->where('id', $id)->first();
        } catch (Exception $e) {
            Log::error('Failed to fetch user from MsGraph: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Subscribe to email notifications.
     */
    public function subscribe()
    {
        $authService = app(GraphAuthService::class);
        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);
        $response = $subscriptionService->subscribeToEmailNotifications($this->ms_id, $this->abn_secret);

        if ($response['id'] ?? false) {
            $this->subscription_id = $response['id'];
            $this->expire_at = Carbon::parse($response['expirationDateTime']);
            $this->save();
        } else {
            Log::error('Failed to subscribe: ', $response);
        }
    }

    /**
     * Revoke email subscription.
     */
    public function revokeSubscription()
    {
        if (!$this->subscription_id) {
            return;
        }
        $authService = app(GraphAuthService::class);
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
            Log::error($response);
        }
    }

    /**
     * Refresh email subscription.
     */
    public function refreshSubscription()
    {
        if (!$this->subscription_id) {
            //\Log::info('No subscription ID found to refresh.');
            return;
        }

        $authService = app(GraphAuthService::class);
        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);
        $response = $subscriptionService->renewEmailNotificationSubscription($this->subscription_id);

        if ($response['success'] ?? false) {
            $this->expire_at = Carbon::parse($response['expirationDateTime']);
            $this->save();
        } else {
            Log::error($response);
        }
    }

    /**
     * Récupère une option de service depuis les données JSON.
     */
    public function getServiceOption(string $serviceKey, string $optionKey, $default = null)
    {
        $servicesOptions = $this->services_options ?? [];
        return $servicesOptions[$serviceKey][$optionKey] ?? $default;
    }

    /**
     * Définit une option de service dans les données JSON.
     */
    public function setServiceOption(string $serviceKey, string $optionKey, $value): void
    {
        $servicesOptions = $this->services_options ?? [];
        $servicesOptions[$serviceKey][$optionKey] = $value;
        $this->services_options = $servicesOptions;
    }

    public function getServiceResult(string $serviceKey, ?string $resultKey = null, $default = null)
    {
        $servicesResults = $this->services_results ?? [];

        // 🔹 Si on veut tout le bloc du service
        if ($resultKey === null) {
            return $servicesResults[$serviceKey] ?? $default;
        }

        // 🔹 Sinon on renvoie la clé précise
        return $servicesResults[$serviceKey][$resultKey] ?? $default;
    }

    public function setServiceResult(string $serviceKey, ?string $resultKey, $value): void
    {
        $servicesResults = $this->services_results ?? [];

        // 🔹 Si on veut remplacer tout le bloc
        if ($resultKey === null) {
            $servicesResults[$serviceKey] = is_array($value) ? $value : (array) $value;
        } else {
            $servicesResults[$serviceKey][$resultKey] = $value;
        }

        $this->services_results = $servicesResults;
    }
}
