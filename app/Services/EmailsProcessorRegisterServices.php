<?php 

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;
use App\Contracts\MsGraph\MsGraphEmailServiceInterface;
use RuntimeException;

class EmailsProcessorRegisterServices
{
    /**
     * Récupère tous les services d'un type spécifique.
     */
    public static function getAll(string $serviceType): array
    {
        $servicesConfig = config("msgraph.{$serviceType}", []);

        if (!is_array($servicesConfig)) {
            throw new RuntimeException("La configuration pour {$serviceType} est invalide.");
        }

        $services = [];
        foreach ($servicesConfig as $className) {
            if (!class_exists($className)) {
                throw new RuntimeException("La classe {$className} n'existe pas.");
            }

            // if (!in_array(MsGraphEmailServiceInterface::class, class_implements($className))) {
            //     throw new RuntimeException("La classe {$className} doit implémenter MsGraphEmailServiceInterface.");
            // }

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
     * Récupère un service spécifique par type et clé.
     */
    public static function get(string $serviceType, string $key): ?array
    {
        $services = self::getAll($serviceType);

        return $services[$key] ?? null;
    }
}
