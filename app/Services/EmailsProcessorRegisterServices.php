<?php 

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;
use App\Contracts\MsGraph\MsGraphEmailServiceInterface;
use RuntimeException;

class EmailsProcessorRegisterServices
{
    /**
     * Récupère et valide toutes les classes de services depuis la configuration.
     */
    protected static function getRegisteredServices(): array
    {
        return config('msgraph.services', []);
    }

    /**
     * Récupère les informations de tous les services (avec ou sans mise en cache).
     */
    public static function getAll(): array
    {
        if (!App::environment('production')) {
            // Pas de mise en cache hors production
            return self::loadServices();
        }

        // Mise en cache en production
        return Cache::rememberForever('msgraph.services', function () {
            return self::loadServices();
        });
    }

    /**
     * Charge et valide toutes les classes de services.
     */
    protected static function loadServices(): array
    {
        $services = [];
        foreach (self::getRegisteredServices() as $className) {
            if (!class_exists($className)) {
                throw new RuntimeException("La classe {$className} n'existe pas.");
            }

            if (!in_array(MsGraphEmailServiceInterface::class, class_implements($className))) {
                throw new RuntimeException("La classe {$className} doit implémenter MsGraphEmailServiceInterface.");
            }

            $key = $className::getKey();
            $services[$key] = [
                'key' => $key,
                'label' => $className::getLabel(),
                'description' => $className::getDescription(),
                'class' => $className,
                'options' => $className::getServicesOptions(),
                'results' => $className::getServicesResults(),
            ];
        }

        return $services;
    }

    /**
     * Récupère un service spécifique par clé.
     */
    public static function get(string $key): array
    {
        $all = self::getAll();

        if (!isset($all[$key])) {
            throw new RuntimeException("Le service avec la clé {$key} n'est pas enregistré.");
        }

        return $all[$key];
    }

    /**
     * Vide le cache pour les services.
     */
    public static function clearCache(): void
    {
        Cache::forget('msgraph.services');
    }
}
