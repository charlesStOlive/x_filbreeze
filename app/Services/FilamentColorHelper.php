<?php

namespace App\Services;

use Filament\Support\Facades\FilamentColor;

class FilamentColorHelper
{
    /**
     * Récupère une couleur Filament et la convertit en hex
     * 
     * @param string $colorName Nom de la couleur (primary, secondary, etc.)
     * @param int $shade Nuance de la couleur (50-900, par défaut 500)
     * @return string Couleur en format hex
     */
    public static function getHexColor(string $colorName, int $shade = 500): string
    {
        try {
            // Récupération directe depuis le panel Filament
            $panel = filament()->getCurrentPanel();
            $colors = $panel?->getColors() ?? [];
            
            if (isset($colors[$colorName])) {
                $color = $colors[$colorName];
                
                // Si c'est déjà une couleur hex, on la retourne
                if (is_string($color) && str_starts_with($color, '#')) {
                    return $color;
                }
            }
            
        } catch (\Exception $e) {
            // En cas d'erreur, continuer vers les couleurs par défaut
        }
        
        // Couleurs par défaut - utiliser directement celles du projet
        return self::getDefaultColors()[$colorName] ?? '#6B7280';
    }
    
    /**
     * Convertit une couleur OKLCH en hex (approximation)
     */
    private static function convertOklchToHex(string $oklch): string
    {
        // Extraction des valeurs OKLCH avec regex
        if (preg_match('/oklch\(([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\)/', $oklch, $matches)) {
            $l = (float) $matches[1]; // Lightness 0-1
            $c = (float) $matches[2]; // Chroma 0-0.4
            $h = (float) $matches[3]; // Hue 0-360
            
            // Conversion approximative OKLCH vers RGB
            // Cette conversion est simplifiée, pour une conversion précise 
            // il faudrait une bibliothèque spécialisée
            
            // Conversion hue en radians
            $hRad = deg2rad($h);
            
            // Approximation de conversion vers RGB
            $a = $c * cos($hRad);
            $b = $c * sin($hRad);
            
            // Conversion Lab vers RGB (approximation)
            $y = ($l + 16) / 116;
            $x = $a / 500 + $y;
            $z = $y - $b / 200;
            
            // Normalisation et conversion vers RGB
            $r = self::clamp($l * 255, 0, 255);
            $g = self::clamp($l * 255 * (1 - abs($a) * 0.1), 0, 255);
            $b_rgb = self::clamp($l * 255 * (1 - abs($b) * 0.1), 0, 255);
            
            return sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b_rgb);
        }
        
        // Si la conversion échoue, retourner une couleur par défaut
        return '#6B7280';
    }
    
    /**
     * Limite une valeur entre min et max
     */
    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
    
    /**
     * Retourne les couleurs par défaut du projet
     */
    private static function getDefaultColors(): array
    {
        return [
            'primary' => '#B24030',
            'secondary' => '#F7D463',
            'tertiary' => '#10B981',
            'success' => '#10B981',
            'warning' => '#F59E0B',
            'danger' => '#EF4444',
            'info' => '#3B82F6',
            'gray' => '#6B7280',
        ];
    }
    
    /**
     * Ajoute de la transparence à une couleur hex
     */
    public static function addTransparency(string $hexColor, float $opacity = 0.5): string
    {
        // Nettoyer la couleur hex
        $hex = ltrim($hexColor, '#');
        
        // Convertir en RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Retourner en format rgba
        return "rgba($r, $g, $b, $opacity)";
    }
}