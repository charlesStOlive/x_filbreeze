<?php 

namespace App\Services\Helpers;

use Exception;
use Illuminate\Support\Facades\Vite;

class ViteHelper
{
    /**
     * Retourne l'URL d'un asset Vite.
     * En mode preview (hot reload), utilise le serveur de dev Vite
     * En mode production/PDF, utilise les fichiers buildés
     */
    public static function viteAsset(string $asset, bool $forceProduction = false): string
    {
        // Si on force la production (pour Browsershot) ou si on est en production
        if ($forceProduction || app()->environment('production') || !static::isDevelopmentServerRunning()) {
            return static::getProductionAssetUrl($asset);
        }

        // En développement avec serveur Vite actif, utilise Laravel Vite standard
        // On doit utiliser Vite::asset() qui retourne une URL complète
        try {
            // Laravel Vite génère des URL absolues avec le serveur de dev
            return Vite::asset($asset);
        } catch (\Exception $e) {
            // Si le serveur Vite ne répond pas, fallback sur la version buildée
            return static::getProductionAssetUrl($asset);
        }
    }

    /**
     * Retourne l'URL d'un asset CSS compilé pour Browsershot
     */
    public static function getCompiledCssPath(string $asset): string
    {
        return static::getProductionAssetUrl($asset);
    }

    /**
     * Vérifie si le serveur de développement Vite est en cours d'exécution
     */
    protected static function isDevelopmentServerRunning(): bool
    {
        if (!app()->environment('local')) {
            return false;
        }

        $hotFile = public_path('hot');
        
        if (!file_exists($hotFile)) {
            return false;
        }

        $hotUrl = trim(file_get_contents($hotFile));
        
        // Teste si le serveur répond (même avec une 404, c'est que le serveur est up)
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'method' => 'HEAD',
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $headers = @get_headers($hotUrl, 1, $context);
        // Si on reçoit n'importe quelle réponse HTTP, le serveur est up
        return $headers !== false && (
            strpos($headers[0], '200') !== false || 
            strpos($headers[0], '404') !== false ||
            strpos($headers[0], '302') !== false
        );
    }

    /**
     * Récupère l'URL de l'asset en production depuis le manifest
     */
    protected static function getProductionAssetUrl(string $asset): string
    {
        $manifestPath = public_path('build/manifest.json');

        if (!file_exists($manifestPath)) {
            throw new Exception("Le manifest Vite est introuvable à : {$manifestPath}");
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (!isset($manifest[$asset]['file'])) {
            throw new Exception("L'asset {$asset} est introuvable dans le manifest.json.");
        }

        return asset('build/' . $manifest[$asset]['file']);
    }
}
