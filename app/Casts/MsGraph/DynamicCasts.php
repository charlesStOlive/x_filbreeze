<?php 

namespace App\Casts\MsGraph;

class DynamicCasts
{
    /**
     * Génère les casts dynamiques basés sur la configuration
     */
    public static function generateCasts(array $servicesConfig): array
    {
        $casts = [];

        foreach ($servicesConfig as $serviceKey => $service) {
            if (!isset($service['options']) || !is_array($service['options'])) {
                continue; // Ignore les services mal formés
            }

            foreach ($service['options'] as $optionKey => $option) {
                $casts["{$serviceKey}_{$optionKey}"] = MsgUserDynamicCast::class . ":{$serviceKey},{$optionKey}";
            }
        }

        return $casts;
    }
}
