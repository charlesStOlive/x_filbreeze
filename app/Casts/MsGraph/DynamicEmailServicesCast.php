<?php

namespace App\Casts\MsGraph;

use RuntimeException;
use Illuminate\Support\Arr;
use App\Services\EmailsProcessorRegisterServices;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class DynamicEmailServicesCast implements CastsAttributes
{
    protected string $serviceKey;
    protected string $optionKey;
    protected string $serviceType; // Type de service : `email-in` ou `email-draft`
    protected string $castType; // Type de cast : `options` ou `results`

    /**
     * Constructeur.
     */
    public function __construct(string $serviceKey, string $optionKey, string $serviceType, string $castType)
    {
        $this->serviceKey = $serviceKey;
        $this->optionKey = $optionKey;
        $this->serviceType = $serviceType;
        $this->castType = $castType;
        // \Log::info('serviceKey = '.$this->serviceKey);
        // \Log::info('optionKey = '.$this->optionKey);
        // \Log::info('serviceType = '.$this->serviceType);
        // \Log::info('castType = '.$this->castType);

        // Valider le service
        $service = EmailsProcessorRegisterServices::get($serviceType, $serviceKey);

        if (!$service) {
            throw new RuntimeException("Le service {$serviceKey} n'est pas enregistré pour {$serviceType}.");
        }
    }

    /**
     * Récupère une valeur depuis l'attribut JSON.
     */
    public function get($model, string $key, $value, array $attributes)
    {
        // \log::info('castType = '.$this->castType);
        $data = json_decode($attributes[$this->castType] ?? '{}', true);
        $resolvedValue = Arr::get($data, "{$this->serviceKey}.{$this->optionKey}");

        // \Log::info("DynamicEmailServicesCast::get - Clé recherchée : {$this->serviceKey}.{$this->optionKey}");
        // \Log::info("Données JSON : " . json_encode($data));
        // \Log::info("Valeur résolue : " . json_encode($resolvedValue));

        return $resolvedValue;
    }

    /**
     * Définit une valeur dans l'attribut JSON.
     */
    public function set($model, string $key, $value, array $attributes)
    {
        $data = json_decode($attributes[$this->castType] ?? '{}', true);
        Arr::set($data, "{$this->serviceKey}.{$this->optionKey}", $value);

        return [$this->castType => json_encode($data)];
    }

    /**
     * Génère les casts dynamiques.
     */
    public static function generateCasts(string $serviceType, string $castType, string $column): array
    {
        $servicesConfig = EmailsProcessorRegisterServices::getAll($serviceType);

        $casts = [];
        foreach ($servicesConfig as $key => $service) {
            $options = $service[$castType] ?? [];
            foreach ($options as $optionKey => $option) {
                $casts["{$column}.{$key}.{$optionKey}"] = self::class . ":{$key},{$optionKey},{$serviceType},{$column}";
            }
        }

        return $casts;
    }
}
