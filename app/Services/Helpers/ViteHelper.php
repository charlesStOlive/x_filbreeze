<?php 

namespace App\Services\Helpers;

use Exception;

class ViteHelper
{
    public static function viteAsset(string $asset, string $buildDirectory = 'pdf'): string
    {
        // En mode PDF, on utilise toujours les fichiers buildés (pas de serveur de dev)
        return static::getProductionAssetUrl($asset, $buildDirectory);
    }

    protected static function getProductionAssetUrl(string $asset, string $buildDirectory): string
    {
        $manifestPath = public_path("{$buildDirectory}/manifest.json");

        if (!file_exists($manifestPath)) {
            return asset($asset);
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (!isset($manifest[$asset]['file'])) {
            throw new Exception("L'asset {$asset} est introuvable dans le manifest.json.");
        }

        return asset("{$buildDirectory}/" . $manifest[$asset]['file']);
    }
}
