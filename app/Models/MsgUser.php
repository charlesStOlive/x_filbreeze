<?php

namespace App\Models;

use Carbon\Carbon;
use App\Casts\MsGraph\DynamicCasts;
use App\Facades\MsGraph\MsgConnect;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\MsgUserSuscription;
use App\Services\Processors\EmailAnalyser;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MsgUser extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        // Charger dynamiquement les casts à partir de la configuration
        $this->casts = DynamicCasts::generateCasts(config('msgraph.services')) ?? [];
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
        //\Log::info('getApiMsgUsersIdsEmails'); 

        if (App::environment('local')) {
            //\Log::info('on est en local');
            // return [];
        }
        
        $connected = MsgConnect::isConnected();
        //\Log::info('Is connected: ' . json_encode($connected));
        
        if (!$connected) {
            MsgConnect::connect(false);
        }

        $users = MsgConnect::getUsers();
        //\Log::info('Users fetched from MS Graph API: ');
        //\Log::info($users);
        
        $users = $users['value'] ?? [];
        $existingEmails = MsgUser::pluck('email')->toArray();
        //\Log::info("Existing emails----------------------------: ");
        //\Log::info($existingEmails);
        
        $filteredUsers = array_filter($users, function ($user) use ($existingEmails) {
            $email = $user['mail'] ?? null;
            if(!$email) {
                return false;
            }
            return !in_array($user['mail'], $existingEmails);
        });
        
        //\Log::info('Filtered users------------------------------: ');
        //\Log::info($filteredUsers);
        $mailAndids = \Arr::pluck($filteredUsers, 'mail', 'id');
        //\Log::info($mailAndids);
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
        //\Log::info('Is connected: ' . json_encode($connected));
        
        if ($connected) {
            $users = MsgConnect::getUsers();
            $users = collect($users['value'] ?? []);
            //\Log::info('Users: ' . json_encode($users));
            return $users->where('id', $id)->first();
        } else {
            return [];
        }
    }

    public function suscribe()
    {
        $reponse = MsgConnect::subscribeToEmailNotifications($this->ms_id, $this->abn_secret);
        // \Log::info('reponse du suscribe');
        // \Log::info($reponse);
        if($reponse['response']['id'] ?? false) {
            $this->suscription_id = $reponse['response']['id']; 
            $this->expire_at = Carbon::parse($reponse['response']['expirationDateTime']);
            $this->save();
        } else {
            // \Log::info('pas ok  apireponse ',$reponse);
        }
        
    }

    public function revokeSuscription()
    {
        if(!$this->suscription_id) {
            return;
        }
        $reponse = MsgConnect::unsubscribeFromEmailNotifications($this->suscription_id);
        //\Log::info('reponse du unsuscribe');
        //\Log::info($reponse);
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
        //\Log::info('reponse du refresh');
        //\Log::info($reponse);
        if($reponse['success'] ?? false) {
            $this->expire_at = Carbon::parse($reponse['response']['expirationDateTime']);
            $this->save();
        } else {
            //\Log::info('pas de sucess ???  ' ,$reponse);
        }
    }



    
}
