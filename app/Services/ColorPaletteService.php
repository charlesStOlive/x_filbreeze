<?php

namespace App\Services;

use OzdemirBurak\Iris\Color\Hex;

class ColorPaletteService
{
    /**
     * Génère une palette complète de couleurs à partir d'une couleur de base
     */
    public function generatePalette(string $baseColor): array
    {
        $hex = new Hex($baseColor);
        
        // Conversion en HSL pour manipuler la luminosité
        $hsl = $hex->toHsl();
        
        return [
            '50'  => $this->adjustLightness($hsl, 95)->toHex()->__toString(),
            '100' => $this->adjustLightness($hsl, 90)->toHex()->__toString(),
            '200' => $this->adjustLightness($hsl, 80)->toHex()->__toString(),
            '300' => $this->adjustLightness($hsl, 70)->toHex()->__toString(),
            '400' => $this->adjustLightness($hsl, 60)->toHex()->__toString(),
            '500' => $baseColor, // Couleur de base
            '600' => $this->adjustLightness($hsl, 40)->toHex()->__toString(),
            '700' => $this->adjustLightness($hsl, 30)->toHex()->__toString(),
            '800' => $this->adjustLightness($hsl, 20)->toHex()->__toString(),
            '900' => $this->adjustLightness($hsl, 10)->toHex()->__toString(),
        ];
    }

    /**
     * Génère une couleur secondaire complémentaire
     */
    public function generateComplementaryColor(string $primaryColor): string
    {
        $hex = new Hex($primaryColor);
        $hsl = $hex->toHsl();
        
        // Rotation de 180° sur le cercle chromatique pour obtenir la couleur complémentaire
        $complementaryHue = ($hsl->hue() + 180) % 360;
        
        // Ajustement de la saturation et luminosité pour harmoniser
        $saturation = max(30, min(70, $hsl->saturation() * 0.8));
        $lightness = max(40, min(70, $hsl->lightness()));
        
        return $hsl->hue($complementaryHue)
                   ->saturation($saturation)
                   ->lightness($lightness)
                   ->toHex()
                   ->__toString();
    }

    /**
     * Génère une couleur secondaire en mode split-complémentaire
     * (une des deux couleurs adjacentes à la complémentaire)
     */
    public function generateSplitComplementarySecondary(string $primaryColor): string
    {
        $hex = new Hex($primaryColor);
        $hsl = $hex->toHsl();
        
        // Rotation de 150° (30° avant la complémentaire)
        $splitHue = ($hsl->hue() + 150) % 360;
        
        // Ajustement harmonieux de la saturation et luminosité
        $saturation = max(30, min(75, $hsl->saturation() * 0.85));
        $lightness = max(35, min(65, $hsl->lightness() * 0.95));
        
        return $hsl->hue($splitHue)
                   ->saturation($saturation)
                   ->lightness($lightness)
                   ->toHex()
                   ->__toString();
    }

    /**
     * Génère une couleur tertiaire en mode split-complémentaire
     * (l'autre couleur adjacente à la complémentaire)
     */
    public function generateSplitComplementaryTertiary(string $primaryColor): string
    {
        $hex = new Hex($primaryColor);
        $hsl = $hex->toHsl();
        
        // Rotation de 210° (30° après la complémentaire)
        $splitHue = ($hsl->hue() + 210) % 360;
        
        // Ajustement harmonieux de la saturation et luminosité
        $saturation = max(25, min(70, $hsl->saturation() * 0.75));
        $lightness = max(40, min(70, $hsl->lightness() * 1.05));
        
        return $hsl->hue($splitHue)
                   ->saturation($saturation)
                   ->lightness($lightness)
                   ->toHex()
                   ->__toString();
    }

    /**
     * Génère une couleur secondaire en mode analogique simple
     * (couleur adjacente dans le sens horaire)
     */
    public function generateAnalogousSecondary(string $primaryColor): string
    {
        $hex = new Hex($primaryColor);
        $hsl = $hex->toHsl();
        
        // Rotation de 30° dans le sens horaire
        $analogousHue = ($hsl->hue() + 30) % 360;
        
        // Légère variation de saturation et luminosité pour créer de l'intérêt
        $saturation = max(25, min(80, $hsl->saturation() * 0.9));
        $lightness = max(30, min(75, $hsl->lightness() * 1.1));
        
        return $hsl->hue($analogousHue)
                   ->saturation($saturation)
                   ->lightness($lightness)
                   ->toHex()
                   ->__toString();
    }

    /**
     * Génère une couleur tertiaire en mode analogique simple
     * (couleur adjacente dans le sens anti-horaire)
     */
    public function generateAnalogousTertiary(string $primaryColor): string
    {
        $hex = new Hex($primaryColor);
        $hsl = $hex->toHsl();
        
        // Rotation de 30° dans le sens anti-horaire
        $analogousHue = ($hsl->hue() - 30 + 360) % 360;
        
        // Variation plus prononcée pour différencier de la secondaire
        $saturation = max(20, min(75, $hsl->saturation() * 0.8));
        $lightness = max(35, min(80, $hsl->lightness() * 1.2));
        
        return $hsl->hue($analogousHue)
                   ->saturation($saturation)
                   ->lightness($lightness)
                   ->toHex()
                   ->__toString();
    }

    /**
     * Génère les couleurs d'état (success, error, warning, info) harmonieuses avec la palette principale
     */
    public function generateStatusColors(string $primaryColor): array
    {
        $hex = new Hex($primaryColor);
        $hsl = $hex->toHsl();
        
        return [
            'success' => $this->generateSuccessColor($hsl),
            'error' => $this->generateErrorColor($hsl),
            'warning' => $this->generateWarningColor($hsl),
            'info' => $this->generateInfoColor($hsl),
        ];
    }

    /**
     * Génère une couleur success harmonieuse (vert adapté à la palette)
     */
    private function generateSuccessColor($primaryHsl): string
    {
        // Base verte (120°) mais ajustée selon la luminosité/saturation de la primary
        $saturation = max(30, min(70, $primaryHsl->saturation() * 0.9));
        $lightness = max(35, min(60, $primaryHsl->lightness() * 0.8));
        
        return $primaryHsl->hue(120) // Vert
                         ->saturation($saturation)
                         ->lightness($lightness)
                         ->toHex()
                         ->__toString();
    }

    /**
     * Génère une couleur error harmonieuse (rouge adapté à la palette)
     */
    private function generateErrorColor($primaryHsl): string
    {
        // Base rouge (0°) mais ajustée selon la palette primaire
        $saturation = max(40, min(80, $primaryHsl->saturation() * 1.1));
        $lightness = max(25, min(50, $primaryHsl->lightness() * 0.75));
        
        return $primaryHsl->hue(0) // Rouge
                         ->saturation($saturation)
                         ->lightness($lightness)
                         ->toHex()
                         ->__toString();
    }

    /**
     * Génère une couleur warning harmonieuse (orange adapté à la palette)
     */
    private function generateWarningColor($primaryHsl): string
    {
        // Base orange (30°) ajustée selon la palette
        $saturation = max(50, min(80, $primaryHsl->saturation() * 0.95));
        $lightness = max(40, min(70, $primaryHsl->lightness() * 1.2));
        
        return $primaryHsl->hue(30) // Orange
                         ->saturation($saturation)
                         ->lightness($lightness)
                         ->toHex()
                         ->__toString();
    }

    /**
     * Génère une couleur info harmonieuse (bleu adapté à la palette)
     */
    private function generateInfoColor($primaryHsl): string
    {
        // Base bleue (210°) ajustée selon la palette
        $saturation = max(30, min(60, $primaryHsl->saturation() * 0.8));
        $lightness = max(35, min(65, $primaryHsl->lightness() * 1.1));
        
        return $primaryHsl->hue(210) // Bleu
                         ->saturation($saturation)
                         ->lightness($lightness)
                         ->toHex()
                         ->__toString();
    }

    /**
     * Génère les variables CSS pour un thème front (avec palette complète)
     */
    public function generateThemeVariables(array $primaryPalette, array $secondaryPalette, ?array $tertiaryPalette = null, ?array $statusColors = null): string
    {
        $css = '';
        
        // Palette complète primary (50-900)
        foreach ($primaryPalette as $shade => $color) {
            $css .= "  --color-primary-{$shade}: {$color};\n";
        }
        
        // Palette complète secondary (50-900)
        foreach ($secondaryPalette as $shade => $color) {
            $css .= "  --color-secondary-{$shade}: {$color};\n";
        }
        
        // Palette complète tertiary (50-900) si définie
        if ($tertiaryPalette) {
            foreach ($tertiaryPalette as $shade => $color) {
                $css .= "  --color-tertiary-{$shade}: {$color};\n";
            }
        }
        
        // Couleurs d'état si définies
        if ($statusColors) {
            foreach ($statusColors as $type => $colorPalette) {
                if (is_array($colorPalette)) {
                    // Si c'est une palette complète (50-900)
                    foreach ($colorPalette as $shade => $color) {
                        $css .= "  --color-{$type}-{$shade}: {$color};\n";
                    }
                } else {
                    // Si c'est juste la couleur de base
                    $css .= "  --color-{$type}: {$colorPalette};\n";
                }
            }
        }
        
        return $css;
    }

    /**
     * Génère les variables CSS pour un thème filament (palette complète également)
     */
    public function generateFilamentThemeVariables(array $primaryPalette, array $secondaryPalette, ?array $tertiaryPalette = null, ?array $statusColors = null): string
    {
        $css = '';
        
        // Palette complète primary (50-900) pour Filament aussi
        foreach ($primaryPalette as $shade => $color) {
            $css .= "  --color-primary-{$shade}: {$color};\n";
        }
        
        // Palette complète secondary (50-900) pour Filament aussi
        foreach ($secondaryPalette as $shade => $color) {
            $css .= "  --color-secondary-{$shade}: {$color};\n";
        }
        
        // Palette complète tertiary (50-900) pour Filament aussi si définie
        if ($tertiaryPalette) {
            foreach ($tertiaryPalette as $shade => $color) {
                $css .= "  --color-tertiary-{$shade}: {$color};\n";
            }
        }
        
        // Couleurs d'état si définies
        if ($statusColors) {
            foreach ($statusColors as $type => $colorPalette) {
                if (is_array($colorPalette)) {
                    // Si c'est une palette complète (50-900)
                    foreach ($colorPalette as $shade => $color) {
                        $css .= "  --color-{$type}-{$shade}: {$color};\n";
                    }
                } else {
                    // Si c'est juste la couleur de base
                    $css .= "  --color-{$type}: {$colorPalette};\n";
                }
            }
        }
        
        return $css;
    }

    /**
     * Met à jour un fichier CSS avec les nouvelles variables de thème
     */
    public function updateCssFile(string $filePath, array $primaryPalette, array $secondaryPalette, ?array $tertiaryPalette = null, ?array $statusColors = null): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        
        // Détermine le type de variables à générer selon le fichier
        $isFilamentFile = strpos($filePath, 'filament') !== false;
        $themeVariables = $isFilamentFile 
            ? $this->generateFilamentThemeVariables($primaryPalette, $secondaryPalette, $tertiaryPalette, $statusColors)
            : $this->generateThemeVariables($primaryPalette, $secondaryPalette, $tertiaryPalette, $statusColors);
        
        // Pattern pour détecter le bloc à remplacer
        $pattern = '/(\/\* color-schema-console-generated-start \*\/\s*\n)(.*?)(\/\* color-schema-console-generated-end \*\/)/s';
        
        $replacement = '$1' . $themeVariables . '  $3';
        
        $updatedContent = preg_replace($pattern, $replacement, $content);
        
        return file_put_contents($filePath, $updatedContent) !== false;
    }

    /**
     * Met à jour le provider Filament avec les nouvelles couleurs
     */
    public function updateFilamentProvider(string $primaryColor, string $secondaryColor, ?array $statusColors = null): bool
    {
        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
        
        if (!file_exists($providerPath)) {
            return false;
        }

        $content = file_get_contents($providerPath);
        
        // Construction du tableau des couleurs
        $colorsArray = [
            "'primary' => '{$primaryColor}'",
            "'secondary' => '{$secondaryColor}'"
        ];
        
        // Ajout des couleurs d'état si définies
        if ($statusColors) {
            foreach ($statusColors as $type => $color) {
                $colorsArray[] = "'{$type}' => '{$color}'";
            }
        }
        
        $colorsString = "[\n                " . implode(",\n                ", $colorsArray) . ",\n            ]";
        
        // Pattern pour détecter et remplacer les couleurs dans le provider
        $pattern = '/->colors\(\s*\[[\s\S]*?\]\s*\)/';
        
        $replacement = "->colors({$colorsString})";
        
        $updatedContent = preg_replace($pattern, $replacement, $content);
        
        return file_put_contents($providerPath, $updatedContent) !== false;
    }

    /**
     * Ajuste la luminosité d'une couleur HSL
     */
    private function adjustLightness($hsl, float $lightness): \OzdemirBurak\Iris\Color\Hsl
    {
        return $hsl->lightness($lightness);
    }

    /**
     * Valide qu'une couleur hex est correcte
     */
    public function validateHexColor(string $color): bool
    {
        return preg_match('/^#[0-9A-F]{6}$/i', $color) === 1;
    }

    /**
     * Normalise une couleur hex (ajoute # si nécessaire)
     */
    public function normalizeHexColor(string $color): string
    {
        $color = ltrim($color, '#');
        return '#' . strtoupper($color);
    }
}