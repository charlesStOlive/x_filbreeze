<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Services\LocaleService;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;


/**
 * @mixin IdeHelperUser
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function msgUserDraft(): HasOne
    {
        return $this->hasOne(MsgUserDraft::class);
    }

    public static function getSystemUser(): self
    {
        return self::where('email', config('notifications.system_user_email'))->firstOrFail();
    }

    /**
     * Obtenir le nom affiché de la timezone de l'utilisateur
     */
    public function getTimezoneDisplayNameAttribute(): string
    {
        return LocaleService::getTimezoneDisplayName($this->timezone ?? 'Europe/Paris');
    }

    /**
     * Obtenir le nom affiché de la locale de l'utilisateur
     */
    public function getLocaleDisplayNameAttribute(): string
    {
        return LocaleService::getLocaleDisplayName($this->locale ?? 'fr_FR');
    }
}
