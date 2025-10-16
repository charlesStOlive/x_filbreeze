<?php

namespace App\Console\Commands;

use App\Services\ColorPaletteService;
use Illuminate\Console\Command;

class GenerateColorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:colors 
                            {--primary= : Couleur primaire (ex: #B24030)}
                            {--secondary= : Couleur secondaire (ex: #F7D463)}
                            {--tertiary= : Couleur tertiaire optionnelle (ex: #10B981)}
                            {--mode= : Mode de génération (manuel, split-comp, simple)}
                            {--swap : Inverser les couleurs secondaire et tertiaire}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère automatiquement les palettes de couleurs pour le projet et met à jour les fichiers CSS';

    /**
     * Service de gestion des palettes de couleurs
     */
    protected ColorPaletteService $colorService;

    /**
     * Fichiers CSS à mettre à jour
     */
    protected array $cssFiles = [
        'resources/css/front/front.css',
        'resources/css/filament/admin/filament.css',
        'resources/css/pdf/pdf.css',
    ];

    public function __construct(ColorPaletteService $colorService)
    {
        parent::__construct();
        $this->colorService = $colorService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎨 Générateur de palettes de couleurs X-Artfil');
        $this->newLine();

        // Récupération du mode de génération
        $mode = $this->getGenerationMode();

        // Récupération des couleurs selon le mode
        [$primaryColor, $secondaryColor, $tertiaryColor] = $this->getColorsForMode($mode);

        if (!$primaryColor) {
            $this->error('Couleur primaire invalide. Veuillez utiliser un format hex valide (ex: #B24030)');
            return Command::FAILURE;
        }

        if (!$secondaryColor && $mode !== 'simple') {
            $this->error('Couleur secondaire invalide. Veuillez utiliser un format hex valide (ex: #F7D463)');
            return Command::FAILURE;
        }

        $this->info("Couleur primaire: {$primaryColor}");
        $this->info("Couleur secondaire: {$secondaryColor}");
        if ($tertiaryColor) {
            $this->info("Couleur tertiaire: {$tertiaryColor}");
        } else {
            $this->info("Couleur tertiaire: non définie");
        }
        $this->newLine();

        // Génération des palettes
        $this->info('Génération des palettes de couleurs...');
        $primaryPalette = $this->colorService->generatePalette($primaryColor);
        $secondaryPalette = $this->colorService->generatePalette($secondaryColor);
        $tertiaryPalette = $tertiaryColor ? $this->colorService->generatePalette($tertiaryColor) : null;
        
        // Génération des couleurs d'état harmonieuses
        $this->info('Génération des couleurs d\'état (success, error, warning, info)...');
        $statusColorsBase = $this->colorService->generateStatusColors($primaryColor);
        $statusColorsPalettes = [];
        
        foreach ($statusColorsBase as $type => $baseColor) {
            $statusColorsPalettes[$type] = $this->colorService->generatePalette($baseColor);
        }

        // Affichage des palettes générées
        $this->displayPalette('Primaire', $primaryPalette);
        $this->displayPalette('Secondaire', $secondaryPalette);
        if ($tertiaryPalette) {
            $this->displayPalette('Tertiaire', $tertiaryPalette);
        }
        
        // Affichage des couleurs d'état
        $this->info('Couleurs d\'état générées:');
        foreach ($statusColorsBase as $type => $color) {
            $this->line("  {$type}: {$color}");
        }
        $this->newLine();

        // Mise à jour des fichiers CSS
        $this->info('Mise à jour des fichiers CSS...');
        $updatedFiles = 0;
        
        foreach ($this->cssFiles as $cssFile) {
            $fullPath = base_path($cssFile);
            
            if ($this->colorService->updateCssFile($fullPath, $primaryPalette, $secondaryPalette, $tertiaryPalette, $statusColorsPalettes)) {
                $this->line("✅ {$cssFile}");
                $updatedFiles++;
            } else {
                $this->line("❌ {$cssFile} (fichier introuvable ou balises manquantes)");
            }
        }

        // Mise à jour du provider Filament
        $this->info('Mise à jour du provider Filament...');
        if ($this->colorService->updateFilamentProvider($primaryColor, $secondaryColor, $statusColorsBase)) {
            $this->line("✅ app/Providers/Filament/AdminPanelProvider.php");
        } else {
            $this->line("❌ app/Providers/Filament/AdminPanelProvider.php (fichier introuvable ou structure incorrecte)");
        }

        $this->newLine();
        $this->info("🎉 Génération terminée ! {$updatedFiles} fichier(s) CSS mis à jour.");
        $this->comment('N\'oubliez pas de recompiler vos assets (npm run build)');

        return Command::SUCCESS;
    }

    /**
     * Récupère le mode de génération
     */
    protected function getGenerationMode(): string
    {
        $mode = $this->option('mode');
        
        if (!$mode) {
            $mode = $this->choice(
                'Quel mode de génération souhaitez-vous utiliser ?',
                ['manuel', 'split-comp', 'simple'],
                'split-comp'
            );
        }

        return $mode;
    }

    /**
     * Récupère les couleurs selon le mode de génération
     */
    protected function getColorsForMode(string $mode): array
    {
        $colors = match ($mode) {
            'manuel' => $this->getManualColors(),
            'split-comp' => $this->getSplitComplementaryColors(),
            'simple' => $this->getAnalogousColors(),
            default => [null, null, null],
        };

        // Inverser secondaire et tertiaire si l'option --swap est activée
        if ($this->option('swap') && isset($colors[1]) && isset($colors[2])) {
            [$colors[1], $colors[2]] = [$colors[2], $colors[1]];
            $this->info('🔄 Couleurs secondaire et tertiaire inversées');
        }

        return $colors;
    }

    /**
     * Mode manuel - toutes les couleurs sont saisies manuellement
     */
    protected function getManualColors(): array
    {
        $primaryColor = $this->getPrimaryColor('Couleur primaire');
        $secondaryColor = $this->getColorInput('secondary', 'Couleur secondaire');
        $tertiaryColor = $this->getColorInput('tertiary', 'Couleur tertiaire');

        return [$primaryColor, $secondaryColor, $tertiaryColor];
    }

    /**
     * Mode split-complémentaire - génère secondaire et tertiaire automatiquement
     */
    protected function getSplitComplementaryColors(): array
    {
        $primaryColor = $this->getPrimaryColor('Couleur primaire');
        
        if (!$primaryColor) {
            return [null, null, null];
        }

        $this->info('Génération automatique des couleurs en mode split-complémentaire...');
        $secondaryColor = $this->colorService->generateSplitComplementarySecondary($primaryColor);
        $tertiaryColor = $this->colorService->generateSplitComplementaryTertiary($primaryColor);

        return [$primaryColor, $secondaryColor, $tertiaryColor];
    }

    /**
     * Mode analogique simple - génère toutes les couleurs à partir de la primaire
     */
    protected function getAnalogousColors(): array
    {
        $primaryColor = $this->getPrimaryColor('Couleur primaire');
        
        if (!$primaryColor) {
            return [null, null, null];
        }

        $this->info('Génération automatique des couleurs en mode analogique...');
        $secondaryColor = $this->colorService->generateAnalogousSecondary($primaryColor);
        $tertiaryColor = $this->colorService->generateAnalogousTertiary($primaryColor);

        return [$primaryColor, $secondaryColor, $tertiaryColor];
    }

    /**
     * Récupère la couleur primaire depuis l'option ou demande à l'utilisateur
     */
    protected function getPrimaryColor(string $label = 'Couleur primaire (format hex, ex: #B24030)'): ?string
    {
        $primary = $this->option('primary');
        
        if (!$primary) {
            $primary = $this->ask($label);
        }

        if (!$primary) {
            return null;
        }

        $primary = $this->colorService->normalizeHexColor($primary);
        
        return $this->colorService->validateHexColor($primary) ? $primary : null;
    }

    /**
     * Récupère une couleur depuis l'option ou demande à l'utilisateur
     */
    protected function getColorInput(string $option, string $label): ?string
    {
        $color = $this->option($option);
        
        if (!$color) {
            $color = $this->ask("{$label} (format hex ex: #F7D463, ou appuyez sur Entrée pour ignorer)", '');
        }

        if (!$color || $color === '') {
            return null;
        }

        $color = $this->colorService->normalizeHexColor($color);
        
        return $this->colorService->validateHexColor($color) ? $color : null;
    }



    /**
     * Affiche une palette de couleurs dans la console
     */
    protected function displayPalette(string $name, array $palette): void
    {
        $this->line("Palette {$name}:");
        foreach ($palette as $shade => $color) {
            $this->line("  {$shade}: {$color}");
        }
        $this->newLine();
    }
}
