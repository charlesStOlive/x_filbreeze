<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Arr;
use Exception;
use Throwable;
use Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Infrastructure\MsGraph\GraphAuthService;
use App\Casts\MsGraph\DynamicEmailServicesCast;
use App\Services\MsGraph\MsGraphSubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\SendsNotifications;


class MsgUserDraft extends Model
{
    use HasFactory, SendsNotifications;

    protected $table = 'msg_user_drafts';
    protected $guarded = ['id'];
    protected $casts = [
        'data_email' => 'json',
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
            $model->revokeSubscription();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        $authService = app(GraphAuthService::class);

        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $users = $authService->guzzle('get', 'users');
        $users = $users['value'] ?? [];

        $existingEmails = MsgUserDraft::pluck('email')->toArray();
        $filteredUsers = array_filter($users, function ($user) use ($existingEmails) {
            return isset($user['mail']) && !in_array($user['mail'], $existingEmails);
        });

        return Arr::pluck($filteredUsers, 'mail', 'id');
    }

    public static function getApiMsgUser($id)
    {
        $authService = app(GraphAuthService::class);

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
        $authService = app(GraphAuthService::class);

        if (!$authService->isConnected()) {
            $authService->connect(false);
        }

        $subscriptionService = app(MsGraphSubscriptionService::class);

        try {
            $response = $subscriptionService->subscribeToDraftNotifications($this->ms_id, $this->abn_secret);

            if ($response['id'] ?? false) {
                $this->subscription_id = $response['id'];
                $this->expire_at = Carbon::parse($response['expirationDateTime']);
                $this->save();
            } else {
                $message = 'Réponse invalide de Microsoft Graph : ' . json_encode($response);
                $this->notifyError('Réponse invalide de Microsoft Graph', json_encode($response));
                throw new Exception($message);
            }
        } catch (Throwable $e) {
            $this->notifyError('Erreur de connexion MsGraph', $e->getMessage(), 'live');

            // Optionnel : relancer l’exception si tu veux
            // throw $e;
        }
    }

    public function revokeSubscription(?string $sucription = null)
    {
        if ($sucription) {
            $this->subscription_id = $sucription;
        }

        if (!$this->subscription_id) {
            return;
        }

        try {
            $authService = app(GraphAuthService::class);
            if (!$authService->isConnected()) {
                $authService->connect(false);
            }

            $subscriptionService = app(MsGraphSubscriptionService::class);
            $response = $subscriptionService->revokeAllSubscriptionsForUser($this->ms_id);

            if ($response['success'] ?? false) {
                $this->subscription_id = null;
                $this->expire_at = null;
                $this->save();

                $this->notifySuccess(
                    'Abonnement révoqué',
                    "La souscription de {$this->email} a bien été annulée."
                );
            } else {
                //\Log::error($response);
                $this->notifyError('Erreur lors de la révocation', json_encode($response));
            }
        } catch (Throwable $e) {
            Log::error("Erreur revokeSubscription : " . $e->getMessage());
            $this->notifyError('Exception revokeSubscription', $e->getMessage());
        }
    }


    /**
     * Renouvellement de l'abonnement.
     */
    public function refreshSubscription()
    {
        try {
            $authService = app(GraphAuthService::class);
            if (!$authService->isConnected()) {
                $authService->connect(false);
            }

            $subscriptionService = app(MsGraphSubscriptionService::class);
            $response = $subscriptionService->renewDraftNotificationSubscription($this->subscription_id);

            if ($response['success'] ?? false) {
                $this->expire_at = Carbon::parse($response['response']['expirationDateTime']);
                $this->save();

                $this->notifySuccess(
                    'Abonnement renouvelé',
                    "La souscription de {$this->email} a été prolongée jusqu’au {$this->expire_at->format('d/m/Y H:i')}."
                );
            } else {
                Log::error($response);
                $this->notifyError('Erreur lors du renouvellement', json_encode($response));
            }
        } catch (Throwable $e) {
            Log::error("Erreur refreshSubscription : " . $e->getMessage());
            $this->notifyError('Exception refreshSubscription', $e->getMessage());
        }
    }

    public function toggleUserLink(): void
    {
        if ($this->user_id) {
            $this->user_id = null;
            $this->save();
            return;
        }

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            throw new Exception("Aucun utilisateur trouvé avec l'email '{$this->email}'");
        }

        $this->user_id = $user->id;
        $this->save();
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
