<?php

namespace App\Models;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\DynamicEmailServicesCast;
use App\Services\EmailsProcessorRegisterServices;
use Illuminate\Database\Eloquent\Factories\HasFactory;



class MsgEmailIn extends Model
{
    use HasFactory;

    protected $casts = [
        'services_options' => 'json',
        'services_results' => 'json',
        'finished_at' => 'datetime',
        'has_error' => 'boolean',
    ];

    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Les casts dynamiques ne sont plus nécessaires avec la nouvelle approche
        // Les données JSON sont maintenant gérées directement via les méthodes helper
    }


    public function msg_email_user()
    {
        return $this->belongsTo(MsgUserIn::class, 'msg_user_in_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
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
