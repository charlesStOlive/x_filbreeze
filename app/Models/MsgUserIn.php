<?php

namespace App\Models;

use Carbon\Carbon;
use App\Facades\MsGraph\MsgConnect;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\DynamicEmailServicesCast;
use App\Services\EmailsProcessorRegisterServices;
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
        // Charger dynamiquement les casts à partir de la configuration
        $servicesConfig = EmailsProcessorRegisterServices::getAll();

        // Générer les casts pour `services`
        $this->casts = array_merge(
            $this->casts,
            DynamicEmailServicesCast::generateCasts($servicesConfig, 'services')
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }



    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted():void
    {
        static::deleted(function ($model) {
            $model->msg_email_ins()->delete();
            $model->revokeSuscription();
        });
    }

    public function msg_email_ins()
    {
        return $this->hasMany(MsgEmailIn::class);
    }

    public static function getApiMsgUsersIdsEmails()
    {
        $connected = MsgConnect::isConnected();
        if (!$connected) {
            MsgConnect::connect(false);
        }
        $users = MsgConnect::getUsers();
        $users = $users['value'] ?? [];
        $existingEmails = MsgUserIn::pluck('email')->toArray();
        $filteredUsers = array_filter($users, function ($user) use ($existingEmails) {
            $email = $user['mail'] ?? null;
            if(!$email) {
                return false;
            }
            return !in_array($user['mail'], $existingEmails);
        });
        $mailAndids = \Arr::pluck($filteredUsers, 'mail', 'id');
        return $mailAndids;
    }

    public static function getLocalUser() {
        return self::get()->pluck( 'email', 'id')->toArray();
    }

    public static function getLocalUserEmail() {
        return self::get()->pluck( 'email', 'email')->toArray();
    }

    public static function getApiMsgUser($id)
    {
        $connected = MsgConnect::isConnected();
        if ($connected) {
            $users = MsgConnect::getUsers();
            $users = collect($users['value'] ?? []);
            return $users->where('id', $id)->first();
        } else {
            return [];
        }
    }

    public function suscribe()
    {
        $reponse = MsgConnect::subscribeToEmailNotifications($this->ms_id, $this->abn_secret);
        if($reponse['response']['id'] ?? false) {
            $this->suscription_id = $reponse['response']['id']; 
            $this->expire_at = Carbon::parse($reponse['response']['expirationDateTime']);
            $this->save();
        } else {
           \Log::info('pas ok  apireponse ',$reponse);
        }
        
    }

    public function revokeSuscription()
    {
        if(!$this->suscription_id) {
            return;
        }
        $reponse = MsgConnect::unsubscribeFromEmailNotifications($this->suscription_id);
        if($reponse['success'] ?? false) {
            $this->suscription_id = null;
            $this->expire_at = null;
            $this->save();
        } else {
            //\Log::info('pas de sucess ???  ');
        }
    }

    public function refreshSuscription()
    {
        $reponse = MsgConnect::renewEmailNotificationSubscription($this->suscription_id);
        if($reponse['success'] ?? false) {
            $this->expire_at = Carbon::parse($reponse['response']['expirationDateTime']);
            $this->save();
        } else {
            \Log::info('refreshSuscription pas de sucess ???  ' ,$reponse);
        }
    }



    
}
