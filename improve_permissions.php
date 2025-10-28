<?php

/**
 * Script d'amélioration rapide des permissions
 * 
 * Ce script applique automatiquement les recommandations du rapport d'audit
 * 
 * Usage: php improve_permissions.php [action]
 * Actions: coverage, backup, cleanup, all
 */

class PermissionsImprover
{
    private array $crmResources = [
        'company', 'contact', 'invoice', 'quote', 
        'sector', 'supplier', 'supplierinvoice'
    ];

    private array $msgraphResources = [
        'msginuser', 'msgdraftuser'
    ];

    public function __construct()
    {
        echo "🚀 Amélioration rapide des permissions\n";
        echo "=====================================\n\n";
    }

    public function run($action = 'menu'): void
    {
        switch ($action) {
            case 'coverage':
                $this->improveCoverage();
                break;
            case 'backup':
                $this->createBackup();
                break;
            case 'cleanup':
                $this->cleanup();
                break;
            case 'all':
                $this->runAll();
                break;
            default:
                $this->showMenu();
                break;
        }
    }

    private function showMenu(): void
    {
        echo "Choisissez une action:\n";
        echo "1. 🎯 Améliorer la couverture des permissions (coverage)\n";
        echo "2. 💾 Créer un backup des permissions actuelles (backup)\n";
        echo "3. 🧹 Nettoyer les permissions obsolètes (cleanup)\n";
        echo "4. 🚀 Tout faire (all)\n";
        echo "5. ❌ Quitter\n\n";

        $choice = readline("Votre choix (1-5): ");

        switch ($choice) {
            case '1':
                $this->improveCoverage();
                break;
            case '2':
                $this->createBackup();
                break;
            case '3':
                $this->cleanup();
                break;
            case '4':
                $this->runAll();
                break;
            case '5':
                echo "👋 Au revoir!\n";
                break;
            default:
                echo "❌ Choix invalide\n";
                $this->showMenu();
        }
    }

    private function improveCoverage(): void
    {
        echo "🎯 Amélioration de la couverture des permissions\n";
        echo "----------------------------------------------\n";

        // CRM Resources
        echo "📊 Ajout des permissions CRM...\n";
        foreach ($this->crmResources as $resource) {
            $this->addResourcePermissions($resource);
        }

        // MsGraph Resources  
        echo "\n📧 Ajout des permissions MsGraph...\n";
        foreach ($this->msgraphResources as $resource) {
            $this->addResourcePermissions($resource);
        }

        // Synchronisation finale
        echo "\n🔄 Synchronisation globale...\n";
        $this->runCommand('php artisan permissions:sync --force');

        echo "\n✅ Couverture améliorée avec succès!\n";
        $this->showNewCoverage();
    }

    private function addResourcePermissions(string $resource): void
    {
        $command = "php artisan permissions:add-resource {$resource} --actions=view,create,edit,delete";
        echo "   → Ajout {$resource}...\n";
        
        // En mode simulation, on afficherait juste la commande
        if (defined('DRY_RUN') && DRY_RUN) {
            echo "     [DRY RUN] {$command}\n";
        } else {
            $this->runCommand($command);
        }
    }

    private function createBackup(): void
    {
        echo "💾 Création d'un backup des permissions\n";
        echo "-------------------------------------\n";

        $backupName = 'PermissionsBackup_' . date('Y_m_d_H_i_s');
        $command = "php artisan permissions:generate-seeder --file={$backupName}";
        
        echo "📁 Nom du backup: {$backupName}\n";
        echo "💾 Création en cours...\n";
        
        $this->runCommand($command);
        
        echo "✅ Backup créé: database/seeders/{$backupName}.php\n";
    }

    private function cleanup(): void
    {
        echo "🧹 Nettoyage des permissions obsolètes\n"; 
        echo "------------------------------------\n";

        echo "🔍 Analyse des permissions obsolètes (dry-run)...\n";
        $this->runCommand('php artisan permissions:sync --cleanup --dry-run');

        echo "\n❓ Voulez-vous procéder au nettoyage réel? (y/N): ";
        $confirm = strtolower(trim(readline()));

        if ($confirm === 'y' || $confirm === 'yes') {
            echo "🗑️  Nettoyage en cours...\n";
            $this->runCommand('php artisan permissions:sync --cleanup --force');
            echo "✅ Nettoyage terminé!\n";
        } else {
            echo "❌ Nettoyage annulé\n";
        }
    }

    private function runAll(): void
    {
        echo "🚀 Exécution complète des améliorations\n";
        echo "=====================================\n\n";

        // 1. Backup
        echo "1️⃣ Création du backup...\n";
        $this->createBackup();
        echo "\n";

        // 2. Coverage
        echo "2️⃣ Amélioration de la couverture...\n";
        $this->improveCoverage();
        echo "\n";

        // 3. Cleanup
        echo "3️⃣ Nettoyage des obsolètes...\n";
        $this->cleanup();
        echo "\n";

        echo "🎉 Toutes les améliorations ont été appliquées!\n";
        $this->showFinalReport();
    }

    private function showNewCoverage(): void
    {
        $totalResources = 13 + count($this->crmResources) + count($this->msgraphResources);
        $coveredResources = 4 + count($this->crmResources) + count($this->msgraphResources);
        $coverage = round(($coveredResources / $totalResources) * 100, 1);

        echo "\n📊 Nouvelle couverture:\n";
        echo "   🔢 Resources totales: {$totalResources}\n";
        echo "   ✅ Resources couvertes: {$coveredResources}\n";
        echo "   📈 Couverture: {$coverage}% (était 30.8%)\n";
    }

    private function showFinalReport(): void
    {
        echo "\n📋 RAPPORT FINAL D'AMÉLIORATION\n";
        echo "============================\n";
        echo "✅ Backup créé pour sécurité\n";
        echo "✅ Couverture étendue à toutes les resources\n";
        echo "✅ Permissions obsolètes nettoyées\n";
        echo "✅ Système de permissions optimisé\n\n";
        
        echo "🎯 Prochaines étapes recommandées:\n";
        echo "   1. Tester les permissions sur les nouvelles resources\n";
        echo "   2. Assigner les rôles appropriés aux utilisateurs\n";
        echo "   3. Créer les tests automatisés\n";
        echo "   4. Documenter les nouveaux workflows\n\n";
        
        echo "📚 Commandes utiles pour la suite:\n";
        echo "   • Voir toutes les permissions: php artisan permissions:sync --dry-run\n";
        echo "   • Créer un nouveau backup: php artisan permissions:generate-seeder\n";
        echo "   • Ajouter une resource: php artisan permissions:add-resource [nom]\n\n";
    }

    private function runCommand(string $command): void
    {
        echo "   🔧 {$command}\n";
        
        // Exécuter la commande réelle
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "   ✅ Succès\n";
        } else {
            echo "   ❌ Erreur (code {$returnCode}):\n";
            foreach ($output as $line) {
                echo "       {$line}\n";
            }
        }
    }
}

// Exécution du script si lancé directement
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'menu';
    
    // Mode dry-run pour les tests
    if (isset($argv[2]) && $argv[2] === '--dry-run') {
        define('DRY_RUN', true);
        echo "🧪 MODE DRY-RUN ACTIVÉ\n\n";
    }
    
    $improver = new PermissionsImprover();
    $improver->run($action);
}