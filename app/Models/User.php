<?php

namespace App\Models;

use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication, MustVerifyEmail
{
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use HasApiTokens;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use InteractsWithEmailAuthentication;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'locale',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'has_email_authentication',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'has_email_authentication' => 'boolean',
        ];
    }

    public function msgUserDraft(): HasOne
    {
        return $this->hasOne(MsgUserDraft::class);
    }
}
