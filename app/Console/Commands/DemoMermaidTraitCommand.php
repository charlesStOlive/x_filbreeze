<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;

class DemoMermaidTraitCommand extends Command
{
    protected $signature = 'demo:mermaid-trait';
    protected $description = 'Démonstration complète du trait HasMermaidStateDiagram avec Invoice';

    public function handle()
    {
        $this->info('🎯 Démonstration du trait HasMermaidStateDiagram');
        $this->newLine();

        // Créer une instance d'Invoice pour la démo
        $invoice = new Invoice();
        
        $this->info('📊 1. Données JSON complètes :');
        $this->line('```json');
        $data = $invoice->getMermaidData();
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('```');
        $this->newLine();

        $this->info('🔗 2. Syntaxe Mermaid générée :');
        $this->line('```mermaid');
        $syntax = $invoice->toMermaidSyntax();
        $this->line($syntax);
        $this->line('```');
        $this->newLine();

        $this->info('🎯 3. État courant (Draft par défaut) :');
        $this->line('```mermaid');
        $current = $invoice->getCurrentStateMermaid();
        $this->line($current);
        $this->line('```');
        $this->newLine();

        $this->info('⚙️ 4. Avec options personnalisées (vertical) :');
        $this->line('```mermaid');
        $vertical = $invoice->toMermaidSyntax(['type' => 'flowchart', 'direction' => 'TB']);
        $this->line($vertical);
        $this->line('```');
        $this->newLine();

        $this->info('🚀 5. Intégration avec Filament InfoList :');
        $this->line('Dans votre Resource ou Page Filament :');
        $this->newLine();
        $this->line('<comment>// Dans votre méthode infolist()</comment>');
        $this->line('MermaidDiagramEntry::make(\'state_diagram\')');
        $this->line('    ->label(\'États et Transitions\')');
        $this->line('    ->apiEndpoint(function ($record) {');
        $this->line('        return route(\'api.states.mermaid-json-from-trait\', [');
        $this->line('            \'model\' => \'Invoice\',');
        $this->line('            \'id\' => $record->id ?? \'new\'');
        $this->line('        ]);');
        $this->line('    })');
        $this->line('    ->type(\'flowchart\')');
        $this->line('    ->direction(\'TB\')');
        $this->line('    ->height(\'500px\'),');
        $this->newLine();

        $this->info('💡 6. Utilisation directe dans le code :');
        $this->line('<comment>// Récupérer les données</comment>');
        $this->line('$invoice = Invoice::find(1);');
        $this->line('$mermaidData = $invoice->getMermaidData();');
        $this->newLine();
        $this->line('<comment>// Générer la syntaxe Mermaid</comment>');
        $this->line('$mermaidSyntax = $invoice->toMermaidSyntax([\'direction\' => \'TB\']);');
        $this->newLine();
        $this->line('<comment>// État courant seulement</comment>');
        $this->line('$currentState = $invoice->getCurrentStateMermaid();');
        $this->newLine();

        $this->info('🔧 7. Utilisation avec d\'autres modèles :');
        $this->line('Pour utiliser ce trait avec d\'autres modèles :');
        $this->newLine();
        $this->line('<comment>// 1. Ajouter le trait au modèle</comment>');
        $this->line('use App\\Traits\\HasMermaidStateDiagram;');
        $this->newLine();
        $this->line('class MyModel extends Model');
        $this->line('{');
        $this->line('    use HasMermaidStateDiagram, HasStates;');
        $this->line('    // ... le reste de votre modèle');
        $this->line('}');
        $this->newLine();
        $this->line('<comment>// 2. Utiliser les méthodes</comment>');
        $this->line('$model = MyModel::first();');
        $this->line('$diagram = $model->getMermaidData();');
        $this->newLine();

        $this->info('✅ Le trait HasMermaidStateDiagram est prêt à être utilisé !');
        $this->newLine();

        // Afficher les statistiques
        $nodeCount = count($data['nodes']);
        $edgeCount = count($data['edges']);
        
        $this->table(
            ['Propriété', 'Valeur'],
            [
                ['États découverts', $nodeCount],
                ['Transitions découvertes', $edgeCount],
                ['Type de diagramme', $data['type']],
                ['Direction', $data['direction']],
                ['Modèle', $data['metadata']['model']],
                ['Cache utilisé', 'Oui (1 heure)'],
                ['API endpoint', '/api/states/Invoice/new/mermaid-json-from-trait'],
            ]
        );

        return Command::SUCCESS;
    }
}